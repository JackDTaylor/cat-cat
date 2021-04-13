<?php /** @noinspection PhpMissingFieldTypeInspection */


namespace CatFetcher\Model;

use CatFetcher\Reference\Hashtag;

class Post extends StoredModel {
	public int    $date;
	public string $text;
	public ?int   $signer;

	public array $authors = [];
	public array $hashtags = [];

	public array $photos = [];
	public array $documents = [];
	public array $attachments = [];

	public ?string $article;

	/** @var Post|string Repost stored as post ID or Post object depnding on 'with_reposts' config option */
	public $repost;

	public object  $stats;

	/**
	 * fromObject
	 *
	 * @param object $object
	 * @return static
	 */
	public static function fromObject(object $object) : self {
		return new static($object->id, (array)$object);
	}

	/**
	 * Post constructor.
	 *
	 * @param string $id
	 * @param array $data
	 */
	public function __construct(string $id, array $data = []) {
		$this->id = $id;

		foreach($data as $key => $value) {
			$this->{$key} = $value;
		}

		if(!$this->authors) {
			$this->authors = [Hashtag::NO_AUTHOR];
		}

		parent::__construct();
	}

	/**
	 * storageKey
	 *
	 * @return string
	 */
	public function storageKey() {
		return $this->id;
	}

	public function getListItemClasses() : array {
		$classes = ['list-item'];

		if($this->article) {
			$classes[] = 'list-item--withLong';
		}

		return $classes;
	}

	/**
	 * getPostTextHtml
	 *
	 * @return string
	 * @noinspection PhpUnused
	 */
	public function getPostTextHtml() {
		$text = $this->text;

		// TODO: FIXME: Use UrlBuilder instead
		$text = preg_replace_callback('/#\S+@catx2/', fn($match) => (
			'<a href="/' . Hashtag::toUrl(Hashtag::uniform($match[0])) . '">' . $match[0] . '</a>'

		), $text);

		return nl2br($text);
	}

	/**
	 * getArticle
	 *
	 * @param Article[] $articles_db
	 * @return Article|null
	 */
	public function getArticle(array $articles_db) : ?Article {
		return $articles_db[ $this->article ] ?? null;
	}

	public function getPostTitle() : string {
		$line = $this->getFirstMeaningfulLine(false);

		if(mb_strlen($line) < 72) {
			return $line;
		}

		return 'Пост ' . $this->id;
	}

	/**
	 * getListItemTitle
	 *
	 * @param Article[] $articles_db
	 * @return string
	 */
	public function getListItemTitle(array $articles_db) : string {
		$article = $this->getArticle($articles_db);

		if($article) {
			return $article->title;
		}

		return $this->getPostTitle() ?: '(без названия)';
	}

	/**
	 * getContentDescription
	 *
	 * @param bool $treat_as_empty
	 * @return string
	 */
	public function getContentDescription($treat_as_empty = false) {
		$types = [];

		if($treat_as_empty == false) {
			if($this->text) {
				$types[] = 'текст';
			}

			if($this->article) {
				$types[] = 'лонг';
			}
		}

		if($this->photos) {
			$types[] = 'фото';
		}

		if($this->repost) {
			$types[] = 'фото';
		}


		if(count($types) >= 2) {
			$types[] = implode(' и ', array_reverse([array_pop($types), array_pop($types)]));
		}

		return implode(', ', $types);
	}

	/**
	 * getContentDescriptionHtml
	 *
	 * @param bool $treat_as_empty
	 * @return string
	 */
	public function getContentDescriptionHtml($treat_as_empty = false) {
		return "<i>&lt;{$this->getContentDescription($treat_as_empty)}&gt;</i>";
	}

	/**
	 * getFirstMeaningfulLine
	 *
	 * @param bool $skip_title
	 * @return string
	 */
	public function getFirstMeaningfulLine($skip_title = true) : string {
		$lines = array_filter(array_map('trim', explode(PHP_EOL, $this->text)));
		$first_line = pos($lines);
		$first_meaningful_line = null;
		$title_skipped = false;

		while(count($lines) > 0) {
			$line = array_shift($lines);

			if(substr(trim($line), 0, 1) != '#') {
				if($skip_title && !$title_skipped && mb_strlen($line) < 72) {
					$title_skipped = true;
					continue;
				}

				$first_meaningful_line = $line;
				break;
			}
		}

		return $first_meaningful_line ?: $first_line ?: $this->getContentDescriptionHtml();
	}
}