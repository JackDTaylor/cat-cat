/** @typedef {{success, queue, input_required: {url}, proceed_url}} WorkerResponse */
/** @typedef {{success, title, status:{class,done,total,errors}}} StatusResponse */

class State {
	static WARMUP  = 'warmup';
	static WORKING = 'working';
	static PAUSING = 'pausing';
	static INPUT   = 'input';
	static PAUSED  = 'paused';
	static DONE    = 'done';
}

async function fetchJson(url) {
	return new Promise((resolve, reject) => {
		$.ajax({
			url,
			dataType: 'json',

			success(result) {
				if(result.success) {
					return resolve(result);
				}

				return reject(new Error(result.error));
			},

			error(xhr, code, error) {
				reject(error);
			}
		});
	});
}

const delay = t => new Promise(r => setTimeout(r, t));

class ImportFrontend {
	/** @var {String} */
	queueId;

	statusUpdateInterval;
	workerPromise;

	isPaused;
	isPauseRequested;
	isInputRequested;
	unpauseUrl;
	inputUrl;

	constructor(queueId) {

		this.$el = $('#import');
		this.$actionButton = this.$el.find('button[name=pause]');
		this.$progress = this.$el.find('> .progress');
		this.$message = this.$el.find('> .message');

		this.queueId = queueId;

		this.handleError = this.handleError.bind(this);
		this.updateStatus = this.updateStatus.bind(this);
		this.togglePause = this.togglePause.bind(this);
		this.onActionButtonClick = this.onActionButtonClick.bind(this);

		this.$actionButton.on('click', this.onActionButtonClick);

		this.state = State.WARMUP;

		// noinspection JSIgnoredPromiseFromCall
		this.runLoop()
	}

	set state(value) {
		console.warn('STATE', value);
		this.isInputRequested = false;

		switch(value) {
			case State.WARMUP:
				this.isPaused = false;
				this.isPauseRequested = false;

				return this.$actionButton
					.text('Запуск...')
					.prop('disabled', true);

			case State.WORKING:
				return this.$actionButton
					.text('Приостановить')
					.prop('disabled', false);

			case State.INPUT:
				this.isInputRequested = true;
				this.isPauseRequested = true;
				this.isPaused         = true;

				return this.$actionButton
					.text('Указать недостающие данные')
					.prop('disabled', false);

			case State.PAUSING:
				this.isPauseRequested = true;

				return this.$actionButton
					.text('Приостановка...')
					.prop('disabled', true);

			case State.PAUSED:
				this.isPauseRequested = false;
				this.isPaused = true;

				return this.$actionButton
					.text('Возобновить')
					.prop('disabled', false);

			case State.DONE:
				this.workerPromise = null;
				clearInterval(this.statusUpdateInterval);

				setTimeout(() => {
					this.title = 'Импорт завершён';
					this.message = 'Все требуемые операции были выполнены.';

					this.$progress.addClass('hidden');
					this.$el.find('> .actions').addClass('hidden');
				}, 1000);
				return this.$actionButton
					.text('Готово')
					.prop('disabled', true);
		}

		console.warn('Unknown state', value);
	}

	set message(value) {
		if(!value) {
			this.$message.find('> .message__title').text('');
			this.$message.find('> .message__text').text('');
			this.$message.addClass('hidden');

			return;
		}

		this.$message.find('> .message__title').text(value.title || '');
		this.$message.find('> .message__text').text(value.text || value);
		this.$message.removeClass('hidden');
	}

	async onActuallyPaused() {
		if(this.isInputRequested) {
			return;
		}

		await delay(1000);
		this.state = State.PAUSED;
	}

	onActionButtonClick() {
		if(this.isInputRequested) {
			location.href = this.inputUrl;
			return;
		}

		return this.togglePause();
	}

	togglePause() {
		if(this.isPauseRequested) {
			return;
		}

		return this.isPaused ? this.unpause() : this.pause();
	}

	pause() {
		this.state = State.PAUSING;

		if(!this.workerPromise) {
			// No worker active, trigger `onActuallyPaused` immediately
			// noinspection JSIgnoredPromiseFromCall
			this.onActuallyPaused();
		}
	}

	unpause() {
		this.state = State.WARMUP;

		return this.runLoop();
	}

	handleError(error) {
		console.error(error);
		this.logError(error?.message || error, true);
	}

	catchErrors(fn, errorHandler = null) {
		errorHandler = errorHandler || this.handleError;

		return function() {
			try {
				fn.apply(this, arguments);
			} catch(error) {
				errorHandler(error);
			}
		};
	}

	async runLoop() {
		if(this.isPaused || this.isPauseRequested) {
			return;
		}

		await this.updateStatus();

		this.statusUpdateInterval = setInterval(this.catchErrors(this.updateStatus), 750);

		this.workerPromise = this.runWorker(`/@import/worker/${this.queueId}`);
	}

	async runWorker(url) {
		let proceedUrl;

		try {
			/** @type {WorkerResponse} */
			const result = await fetchJson(url);

			if(result.input_required) {
				this.requireUserInput(result.input_required);
			}

			proceedUrl = result.proceed_url;
		} catch(error) {
			this.handleError(error);

			proceedUrl = url;
		}

		if(!this.isPauseRequested && !this.isPaused && !this.isInputRequested) {
			this.state = State.WORKING;
		}

		if(!proceedUrl) {
			this.onQueueDone();
			return true;
		}

		if(this.isPauseRequested) {
			this.onActuallyPaused();

			this.unpauseUrl = proceedUrl;
			return false;
		}

		return this.workerPromise = this.runWorker(proceedUrl);
	}

	async onQueueDone() {
		this.state = State.DONE;

		await this.updateStatus();
	}

	errors = {};

	async updateStatus(force = false) {
		if(this.isPaused && !force) {
			return;
		}

		try {
			/** @type {StatusResponse} */
			const response = await fetchJson(`/@import/status/${this.queueId}`);

			this.title = response.title;

			if(response.status) {
				this.setProgress(response.status.done, response.status.total);

				if(response.status.errors && response.status.errors.length > 0) {
					this.errors[ response.status.class ] = response.status.errors;

					this.renderErrors();
				}
			}
		} catch(error) {
			console.error(`Не удалось обновить статус: ${error?.message || error}`);
		}
	}

	set title(value) {
		this.$el.find('h1').text(value);
	}

	set progressDone(value) {
		value = Math.round(Number(value) || 0);

		this.$progress.find('.progress-description__item--done').text(value);
	}

	set progressTotal(value) {
		value = Math.round(Number(value) || 0);

		this.$progress.find('.progress-description__item--total').text(value);
	}

	set progressPercentage(value) {
		value = Math.round((Number(value) || 0) * 10) / 10;

		this.$progress.find('.progress-description__item--percentage').text(`${value}%`);
		this.$progress.find('.progress__bar').width(`${value}%`);
	}

	setProgress(done, total) {
		this.$progress.find('.progress-description').removeClass('hidden');

		this.progressDone = done;
		this.progressTotal = total;
		this.progressPercentage = (done / total) * 100;
	}

	renderErrors() {
		const $errors = this.$el.find('> .errors');

		$errors.find('> .error-list').children('.error:not(.fixed)').remove();

		for(const jobClass of Object.keys(this.errors)) {
			for(const error of this.errors[jobClass]) {
				this.logError(error);
			}
		}

		if($errors.find('> .error-list').is(':empty')) {
			$errors.addClass('hidden');
		}
	}

	logError(errorMessage, fixed = false) {
		const $errors = this.$el.find('> .errors');

		const $item = $('<div>').addClass('error-list__item error').toggleClass('fixed', fixed);

		$item.append($('<div>').addClass('error__message').text(errorMessage));
		$item.append($('<div>').addClass('error__date').text(new Date().toLocaleTimeString()));

		$errors.removeClass('hidden');
		$errors.find('> .error-list').prepend($item);
	}

	requireUserInput(config) {
		this.inputUrl = config.url;
		this.state = State.INPUT;

		this.message = {
			title: 'Требуется ввод данных',
			text: 'В процессе импорта возникла необходимость в заполнении недостающих данных.',
		};
	}
}

$(() => {
	if(window?.FrontendData?.queueId) {
		window.Application = new ImportFrontend(window?.FrontendData?.queueId);
	}
});