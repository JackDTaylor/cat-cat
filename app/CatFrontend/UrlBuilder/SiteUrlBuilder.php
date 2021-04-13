<?php


namespace CatFrontend\UrlBuilder;


use CatFetcher\Model\Document;
use CatFetcher\Model\Photo;
use CatFetcher\Model\Post;
use CatFetcher\Reference\Hashtag;

class SiteUrlBuilder extends UrlBuilder {
	public function getHomepageUrl(): string {
		return '/';
	}

	public function getHashtagUrl(string $hashtag): string {
		return '/' . Hashtag::toUrl($hashtag);
	}

	public function getFileUrl(Document|Photo $file): string {
		return "/storage/files/{$file->local_url}";
	}

	public function getAssetsPath(): string {
		return '/assets';
	}

	public function getPostUrl(Post $post): string {
		$author = pos($post->authors);
		return '/' . Hashtag::toUrl($author) . '/' . $post->id;
	}
}