<?php


namespace CatFrontend\UrlBuilder;


use CatFetcher\Model\Document;
use CatFetcher\Model\Photo;
use CatFetcher\Model\Post;

abstract class UrlBuilder {
	abstract public function getHomepageUrl() : string;
	abstract public function getHashtagUrl(string $hashtag) : string;
	abstract public function getFileUrl(Document|Photo $file) : string;
	abstract public function getAssetsPath() : string;
	abstract public function getPostUrl(Post $post) : string;
}