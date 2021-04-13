<?php


namespace CatFetcher\Reference;


class Hashtag {
	public const AUTHOR    = 'author';
	public const OTHER     = 'other';

	public const NO_AUTHOR = '#автор_не_указан@catx2';

	/**
	 * Uniforms hashtag
	 *
	 * @param $hashtag
	 * @return string
	 */
	public static function uniform($hashtag) {
		$hashtag = trim(mb_strtolower((string)$hashtag));
		$hashtag =preg_replace('#@catx$#', '@catx2', $hashtag);

		return $hashtag;
	}

	/**
	 * Converts hashtag to url part
	 *
	 * @param $hashtag
	 * @return string
	 */
	public static function toUrl($hashtag) : string {
		return urlencode(pos(explode('@', trim($hashtag, '#'))));
	}

	/**
	 * getTextHashtags
	 *
	 * @param $text
	 * @return array
	 */
	public static function parseFromString($text) : array {
		if(!preg_match_all('/#[-_0-9A-ZА-ЯЁ@]+@catx2?/ui', $text, $hashtags)) {
			return [];
		}

		return array_map(fn($x) => static::uniform($x), $hashtags[0] ?? []);
	}
}