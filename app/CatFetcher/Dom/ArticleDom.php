<?php


namespace CatFetcher\Dom;

use CatFetcher\Model\Article;
use CatFetcher\Model\Photo;
use DiDom\Document;
use DiDom\Element;
use DiDom\Exceptions\InvalidSelectorException;
use Exception;

class ArticleDom extends Document {
	public string $code;

	public ?string $title;
	protected array $segments;
	protected Element $current_element;

	/**
	 * ArticleDom constructor.
	 *
	 * @param string $code
	 * @param string $html
	 * @throws InvalidSelectorException
	 */
	public function __construct(string $code, string $html) {
		parent::__construct($html);

		$this->code = $code;

		$this->parse();
	}

	/**
	 * parseDefaultTitle
	 *
	 * @return string|null
	 * @throws InvalidSelectorException
	 */
	protected function parseDefaultTitle() {
		$title = $this->first('head title');

		if(!$title) {
			return null;
		}

		return $title->text();
	}

	/**
	 * parseText
	 *
	 * @param Element $element
	 * @return array
	 */
	protected function parseText(Element $element) {
		return [
			'type' => 'text',
			'content' => $element->html(),
		];
	}

	/**
	 * parseCaptions
	 *
	 * @param Element|null $caption
	 * @return array|mixed
	 */
	protected function parseCaptions(?Element $caption) {
		if(!$caption) {
			return [];
		}

		return json_decode($caption->attr('data-captions'));
	}

	/**
	 * parseVideos
	 *
	 * @param Element $wrapper
	 * @param array $captions
	 * @return array|null
	 * @throws InvalidSelectorException
	 */
	protected function parseVideos(Element $wrapper, array $captions) {
		if(
			$wrapper->classes()->contains('article_object_video_blocked') ||
			$wrapper->classes()->contains('VideoRestriction')
		) {
			return null;
		}

		$video_tags = $wrapper->find('video, iframe, a.vv_preview');

		if(count($video_tags) != 1) {
//			dpr($video_tags);
			$this->error('TODO: Multiple or no videos found', $wrapper->html());
		}

		$videos = [];
		foreach($video_tags as $index => $video) {
			if($video->matches('a.vv_preview')) {
				$preview = $video->first('img.vv_img');

				$videos[] = [
					'type'    => 'link',
					'href'    => $video->attr('href'),
					'preview' => $preview ? $preview->attr('src') : null,
					'caption' => $captions[ $index ] ?? '',
				];

				continue;
			}

			$src = $video->attr('src');

			if(!$src) {
				if($source = $video->first('source')) {
					$src = $source->attr('src');
				}
			}

			if(!$src) {
				$this->error('Unable to find video source', $video->html());
			}

			$videos[] = [
				'type'    => $video->tag,
				'src'     => $video->attr('src'),
				'caption' => $captions[ $index ] ?? '',
			];
		}

		return [
			'type' => 'videos',
			'items' => $videos,
		];
	}

	/**
	 * parseImages
	 *
	 * @param Element $wrapper
	 * @param array $captions
	 * @return array|mixed|null
	 * @throws InvalidSelectorException
	 */
	protected function parseImages(Element $wrapper, array $captions) {
		$image_sizes = [];
		$images = [];

		$classes = $wrapper->classes();

		if($classes->contains('article_object_sizer_wrap') || $classes->contains('article_photo_carousel')) {
			$image_sizes = json_decode($wrapper->attr('data-sizes')) ?: [];
		}

		if($classes->contains('article_object_video')) {
			return $this->parseVideos($wrapper, $captions);
		}

		if(!$image_sizes) {
			return $this->error('Unable to find data-sizes attribute', $wrapper->html());
		}

		foreach($image_sizes as $index => $item) {
			$image = $item->z ?? $item->y ?? $item->x ?? $item->m ?? $item->s ?? null;

			if(!$image) {
				continue;
			}

			[$image_url, $width, $height] = $image;
			$caption = $captions[$index] ?? '';

			$images[] = new Photo($image_url, $width, $height, $caption);
		}

		return [
			'type' => 'images',
			'items' => $images,
		];
	}

	/**
	 * parseEmbed
	 *
	 * @param Element $embed
	 * @param array $captions
	 * @return array
	 */
	protected function parseEmbed(Element $embed, array $captions) {
		return [
			'type' => 'embed',
			'id' => $embed->attr('id'),
			'captions' => $captions,
		];
	}

	/**
	 * parseFigure
	 *
	 * @param Element $element
	 * @return array|mixed|null
	 * @throws InvalidSelectorException
	 */
	protected function parseFigure(Element $element) {
		$captions = $this->parseCaptions($element->first('figcaption'));

		if($sizer_content = $element->first('.article_figure_sizer_content')) {
			$images_wrapper = $sizer_content->firstChild();

			if(!$images_wrapper) {
				return $this->error('Unable to parse `.figure_sizer_content`');
			}

			if(!$images_wrapper->classes()->getAll()) {
				if($images_wrapper->first('video')) {
					// For strange embedded raw videos
					return $this->parseVideos($images_wrapper, $captions);
				}
			}

			return $this->parseImages($images_wrapper, $captions);
		}

		if($carousel = $element->first('.article_photo_carousel')) {
			return $this->parseImages($carousel, $captions);
		}

		if($embed = $element->first('.article_figure_content > .article_object_embed')) {
			return $this->parseEmbed($embed, $captions);
		}

		if($audios = $element->find('.article_figure_content > .audio_item, .AudioPlaylistRoot > .audio_item')) {
			return $this->parseAudios($audios, $element->first('.audioPlaylistSnippet__title_link'));
		}

		if($voting = $element->first('.article_figure_content > .Voting')) {
			return $this->parseVoting($voting, $captions);
		}

		if($podcast = $element->first('.article_figure_content > .snippet_type_podcast')) {
			return $this->parsePodcast($podcast, $captions);
		}

		if($document_image = $element->first('.article_figure_content > div[class=""] > img[src^="/doc"]')) {
			if($url = $document_image->attr('src')) {
				$url = "https://vk.com{$url}&api=1&no_preview=1";

				return [
					'type' => 'images',
					'items' => [new Photo($url, 0, 0, pos($captions))],
				];
			}
		}

		return $this->error('Unable to parse `figure`');
	}

	/**
	 * parsePodcast
	 *
	 * @param Element $podcast
	 * @param array $captions
	 * @return array|mixed
	 * @throws InvalidSelectorException
	 */
	protected function parsePodcast(Element $podcast, array $captions) {
		$button = $podcast->first('button.snippet__button') ?: $this->error('No podcast button');

		if(!preg_match("#Podcast\.onOpenClick\(this,\s*['\"]([-_\d]+)['\"]\)#", $button->attr('onclick') ?: '', $id_match)) {
			return $this->error('No podcast button onclick', $button->html());
		}

		$url = "https://vk.com/podcast{$id_match[1]}";

		$title = $podcast->first('.snippet__title') ?: $this->error('No podcast title');
		$title = trim($title->text());

		return [
			'type' => 'podcast',
			'url' => $url,
			'title' => $title,
			'caption' => pos($captions),
		];
	}

	/**
	 * parseVoting
	 *
	 * @param Element $voting
	 * @param array $captions
	 * @return array
	 */
	protected function parseVoting(Element $voting, array $captions) {
		return [
			'type' => 'voting',
			'raw' => $voting->html(),
			'captions' => $captions,
		];
	}

	/**
	 * parseAudios
	 *
	 * @param array $elements
	 * @param Element|null $playlist_header
	 * @return array
	 */
	protected function parseAudios(array $elements, ?Element $playlist_header) {
		$audios = [];

		foreach($elements as $element) {
			[,,,$title,$author,,,,,,,,,,,$data] = json_decode($element->attr('data-audio'));

			$audios[] = (object)[
				'author' => $author,
				'title' => $title,
				'duration' => $data->duration ?? 0,
				'id' => $data->content_id ?? '',
			];
		}

		$playlist_title = null;
		$playlist_url = null;

		if($playlist_header) {
			$playlist_title = trim($playlist_header->text());
			$playlist_url = 'https://vk.com' . $playlist_header->attr('href');
		}

		return [
			'type'  => 'playlist',
			'title' => $playlist_title,
			'url'   => $playlist_url,
			'items' => $audios,
		];
	}

	/**
	 * parseHeading
	 *
	 * @param Element $element
	 * @return array
	 */
	protected function parseHeading(Element $element) {
		return [
			'type' => 'heading',
			'tag' => $element->tag,
			'content' => $element->text(),
		];
	}

	/**
	 * parseSegment
	 *
	 * @param Element $element
	 * @return array|mixed|string|null
	 * @throws InvalidSelectorException
	 * @throws Exception
	 */
	protected function parseSegment(Element $element) {
		if($element->tag == 'h1') {
			$this->title = $element->text();
			return null;
		}

		if($element->classes()->contains('article__info_line')) {
			return null;
		}

		if(in_array($element->tag, ['p', 'ul', 'ol', 'cite', 'blockquote', 'pre'])) {
			return $this->parseText($element);
		}

		if($element->tag == 'figure') {
			return $this->parseFigure($element);
		}

		if(in_array($element->tag, ['h2', 'h3', 'h4', 'h5'])) {
			return $this->parseHeading($element);
		}

		throw new Exception("Unknown segment in article @{$this->code}");
	}

	/**
	 * parse
	 *
	 * @return Article
	 * @throws InvalidSelectorException
	 * @throws Exception
	 */
	public function parse() : Article {
		$body = $this->find('.articleView__content_list > .articleView__content > .article');

		if(count($body) != 1) {
			throw new Exception("Article has multiple or no bodies: @{$this->code}");
		}

		/** @var Element $body */
		$body = pos($body);

		$this->title = $this->parseDefaultTitle();
		$this->segments = [];

		foreach($body->children() as $child) {
			$this->current_element = $child;

			$segment = $this->parseSegment($child);

			if(!$segment) {
				continue;
			}

			$this->segments[] = $segment;
		}

		return new Article($this->code, $this->title, $this->segments);
	}

	/**
	 * error
	 *
	 * @param mixed ...$messages
	 * @return mixed
	 */
	protected function error(...$messages) {
		$messages[] = $this->code;
		$messages[] = $this->current_element ? $this->current_element->html() : '[No current element]';

		return dpr(...$messages);
	}
}