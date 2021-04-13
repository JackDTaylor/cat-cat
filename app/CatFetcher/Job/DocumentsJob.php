<?php


namespace CatFetcher\Job;


use CatFetcher\Model\Document;
use CatFetcher\Queue\Queue;
use CatFetcher\Util\Downloader;
use CatFetcher\Util\Logger;
use Exception;
use Generator;

class DocumentsJob extends Job {
	/** @return string */
	public static function title() {
		return 'Загрузка документов';
	}

	protected array $documents;
	protected int $total;

	/**
	 * init
	 *
	 * @param Queue $queue
	 */
	protected function init(Queue $queue) {
		parent::init($queue);

		$this->documents = Document::repository();
		$this->total = count($this->documents);
	}

	/**
	 * @inheritDoc
	 * @return Generator
	 */
	public function operations() {
		foreach($this->documents as $file) {
			yield function(DocumentsJob $job) use($file) {
				yield $job->processDocument($file);
			};
		}
	}

	/**
	 * processDocument
	 *
	 * @param Document $document
	 * @return Document
	 * @throws Exception
	 */
	protected function processDocument(Document $document) {
		if(isset($this->db->files[ $document->local_url ])) {
			return $document;
		}

		Logger::logProgress('Downloading documents', count($this->result), count($this->documents));

		$this->db->files[ $document->local_url ] = Downloader::downloadFile($document->download_url);
		$this->db->documents[ $document->local_url ] = $document;

		return $document;
	}
}