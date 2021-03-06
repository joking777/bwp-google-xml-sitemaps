<?php

use Symfony\Component\CssSelector\CssSelector;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class BWP_Sitemaps_Sitemap_Exclude_Items_Functional_Test extends BWP_Sitemaps_PHPUnit_WP_Functional_TestCase
{
	protected static $wp_options = array(
		'bwp_gxs_generator_exclude_terms_by_slugs' => ''
	);

	public function setUp()
	{
		parent::setUp();

		self::reset_posts_terms();
	}

	public function get_extra_plugins()
	{
		$fixtures_dir = dirname(__FILE__) . '/data/fixtures';

		return array(
			$fixtures_dir . '/post-types-and-taxonomies.php' => 'bwp-gxs-fixtures/post-types-and-taxonomies.php',
			$fixtures_dir . '/excluded-terms-slugs.php' => 'bwp-gxs-fixtures/excluded-terms-slugs.php',
		);
	}

	protected static function set_plugin_default_options()
	{
		self::update_option(BWP_GXS_GENERATOR, array(
			'input_exclude_post_type' => '',
			'input_exclude_taxonomy'  => '',
			'enable_sitemap_taxonomy' => 'yes',
			'enable_cache'            => ''
		));

		self::update_option(BWP_GXS_EXCLUDED_POSTS, array());
		self::update_option(BWP_GXS_EXCLUDED_TERMS, array());
	}

	public function test_should_exclude_sitemaps_correctly()
	{
		$this->create_posts('post', 1);
		$this->create_posts('movie', 1);

		$this->create_terms('category', 1);
		$this->create_terms('post_tag', 1);

		self::set_options(BWP_GXS_GENERATOR, array(
			'input_exclude_post_type' => 'movie',
			'input_exclude_taxonomy'  => 'post_tag'
		));

		CssSelector::disableHtmlExtension();

		$crawler = self::get_crawler_from_url($this->plugin->get_sitemap_index_url());

		$this->assertCount(1, $crawler->filter('default|sitemapindex default|sitemap default|loc:contains("' . $this->plugin->get_sitemap_url('post') . '")'));
		$this->assertCount(0, $crawler->filter('default|sitemapindex default|sitemap default|loc:contains("' . $this->plugin->get_sitemap_url('post_movie') . '")'));
		$this->assertCount(1, $crawler->filter('default|sitemapindex default|sitemap default|loc:contains("' . $this->plugin->get_sitemap_url('taxonomy_category') . '")'));
		$this->assertCount(0, $crawler->filter('default|sitemapindex default|sitemap default|loc:contains("' . $this->plugin->get_sitemap_url('taxonomy_post_tag') . '")'));
	}

	public function test_should_exclude_posts_correctly_if_specified()
	{
		$this->create_posts('post');
		$this->create_posts('movie');

		self::update_option(BWP_GXS_EXCLUDED_POSTS, array(
			'post'  => '1,2,3',
			'movie' => '8,9,10'
		));

		CssSelector::disableHtmlExtension();

		$crawler = self::get_crawler_from_url($this->plugin->get_sitemap_url('post'));

		$this->assertCount(2, $crawler->filter('default|urlset default|url default|loc'));

		$crawler = self::get_crawler_from_url($this->plugin->get_sitemap_url('post_movie'));

		$this->assertCount(2, $crawler->filter('default|urlset default|url default|loc'));
	}

	public function test_should_exclude_terms_correctly_if_specified()
	{
		$this->prepare_for_taxonomy_tests();

		self::update_option(BWP_GXS_EXCLUDED_TERMS, array(
			'category' => '1,2,3',
			'genre'    => '8,9,10'
		));

		CssSelector::disableHtmlExtension();

		$crawler = self::get_crawler_from_url($this->plugin->get_sitemap_url('taxonomy_category'));

		$this->assertCount(2, $crawler->filter('default|urlset default|url default|loc'));

		$crawler = self::get_crawler_from_url($this->plugin->get_sitemap_url('taxonomy_genre'));

		$this->assertCount(2, $crawler->filter('default|urlset default|url default|loc'));
	}

	public function test_should_exclude_terms_using_slugs_correctly()
	{
		$this->prepare_for_taxonomy_tests();

		self::update_option('bwp_gxs_generator_exclude_terms_by_slugs', 'yes');

		CssSelector::disableHtmlExtension();

		$crawler = self::get_crawler_from_url($this->plugin->get_sitemap_url('taxonomy_category'));

		$this->assertCount(2, $crawler->filter('default|urlset default|url default|loc'));

		$crawler = self::get_crawler_from_url($this->plugin->get_sitemap_url('taxonomy_genre'));

		$this->assertCount(2, $crawler->filter('default|urlset default|url default|loc'));
	}

	public function test_should_exclude_posts_using_terms_correctly()
	{
		// this will exclude post id 1 and movie id 6
		$this->prepare_for_taxonomy_tests();

		// post id 2 and movie id 7 belong to unexcluded terms
		$this->factory->term->add_post_terms(2, array(4,5), 'category');
		$this->factory->term->add_post_terms(7, array(11,12), 'genre');

		// exclude post id 3 and movie id 8 as well
		$this->factory->term->add_post_terms(3, array(1,4), 'category');
		$this->factory->term->add_post_terms(8, array(8,11), 'genre');

		// movie id 9 is excluded even if it does not belong to any excluded
		// genre, because it belongs to an excluded category
		$this->factory->term->add_post_terms(9, array(1), 'category');
		$this->factory->term->add_post_terms(9, array(11,12), 'genre');

		self::update_option(BWP_GXS_EXCLUDED_TERMS, array(
			'category' => '1,2,3',
			'genre'    => '8,9,10'
		));

		self::set_options(BWP_GXS_GENERATOR, array(
			'enable_exclude_posts_by_terms' => 'yes'
		));

		$crawler = self::get_crawler_from_url($this->plugin->get_sitemap_url('post'));

		$this->assertCount(3, $crawler->filter('default|urlset default|url default|loc'), '2 out of 5 posts should be excluded (id 1 and 3)');

		$crawler = self::get_crawler_from_url($this->plugin->get_sitemap_url('post_movie'));

		$this->assertCount(2, $crawler->filter('default|urlset default|url default|loc'), '3 out of 5 movies should be excluded (id 6, 8 and 9)');
	}

	protected function prepare_for_taxonomy_tests()
	{
		$this->load_fixtures('post-types-and-taxonomies.php');

		bwp_gxs_register_custom_post_types();
		bwp_gxs_register_custom_taxonomies();

		$posts = $this->create_posts('post');
		$movies = $this->create_posts('movie');

		$categories = $this->create_terms('category');
		$genres = $this->create_terms('genre');

		$this->factory->term->add_post_terms($posts[0], $categories, 'category');
		$this->factory->term->add_post_terms($movies[0], $genres, 'genre');

		self::commit_transaction();
	}
}
