<?php


namespace CatFetcher\Job;


use CatFetcher\Model\Photo;
use CatFetcher\Queue\Queue;
use CatFetcher\Util\Downloader;
use CatFetcher\Util\Logger;
use Exception;
use Generator;

class PhotosJob extends Job {
	/** @return string */
	public static function title() {
		return 'Загрузка фотографий';
	}

	protected array $photos;
	protected int $total;

	/**
	 * init
	 *
	 * @param Queue $queue
	 */
	protected function init(Queue $queue) {
		parent::init($queue);

		$this->photos = Photo::repository();
		$this->total = count($this->photos);
	}

	/**
	 * @inheritDoc
	 * @return Generator
	 */
	public function operations() {
		foreach($this->photos as $file) {
			yield function(PhotosJob $job) use ($file) {
				yield $job->processPhoto($file);
			};
		}
	}

	/**
	 * @param Photo $photo
	 * @return Photo
	 * @throws Exception
	 */
	protected function processPhoto(Photo $photo) {
		if(isset($this->db->files[ $photo->local_url ])) {
			return $photo;
		}

		Logger::logProgress('Downloading photos', count($this->result), count($this->photos));

		$this->db->files[ $photo->local_url ] = Downloader::downloadFile($photo->download_url);
		$this->db->photos[ $photo->local_url ] = $photo;

		return $photo;
	}
}