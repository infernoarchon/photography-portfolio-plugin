<?php


namespace Photography_Portfolio\Frontend;


use Photography_Portfolio\Settings\Gallery\lightGallery;

class Public_View {


	/**
	 * @var string - Where all the scripts and styles live
	 */
	protected $build_directory_url;


	/**
	 * Public_View constructor.
	 */
	public function __construct() {

		// Hook into WordPress
		add_action( 'wp_head', [ $this, 'detect_javascript' ] );
		add_filter( 'body_class', [ $this, 'adjust_body_class' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );

		// Adjust .PP_Wrapper classes
		if ( ! phort_has_theme_support() ) {
			add_filter( 'phort_get_class', [ $this, 'adjust_wrapper_class' ], 10, 2 );
		}


		// Add Photoswipe template
		// @TODO: This probably doesn't really belong here.
		if ( 'photoswipe' === phort_get_option( 'popup_gallery' ) ) {
			add_action( 'get_footer', [ $this, 'display_photoswipe_html' ], 1000 );
		}

		$this->build_directory_url = CLM_PLUGIN_DIR_URL . 'public/build/';
	}


	/**
	 * @return bool
	 */
	public function enqueue() {

		// Register scripts & styles
		$this->register();


		/**
		 * Enqueue scripts and styles only in portfolio
		 */
		if ( ! apply_filters( 'phort/enqueue', phort_is_portfolio() ) ) {
			return false;
		}

		// Enqueue style
		wp_enqueue_style( 'phort-style' );


		// Enqueue dependencies dynamically
		if ( in_array( phort_slug_current(), [ 'masonry', 'masonry-hovercard' ] ) ) {
			wp_enqueue_script( 'jquery-masonry' );
		}

		// HOTFIX:
		// @TODO: This is a hotfix. We need a better way to handle the (currently) 2 popup gallery scripts.
		$gallery = phort_get_option( 'popup_gallery' );
		if ( 'lightgallery' === $gallery ) {
			wp_enqueue_script( 'phort-gallery-lightgallery' );
			wp_enqueue_style( 'phort-gallery-lightgallery' );
		}
		if ( 'photoswipe' === $gallery ) {

			wp_enqueue_style('photoswipe-default-skin'); // Explicit queuing
			wp_enqueue_style( 'photoswipe' );

			wp_enqueue_script( 'photoswipe-ui-default' ); // Explicit queuing is necessary, dependencies might get
			wp_enqueue_script( 'photoswipe' );
		}


		// Enqueue photography-portfolio.js last
		wp_enqueue_script( 'phort-app' );
	}


	/**
	 * Register Scripts and Styles
	 * This doesn't enqueue anything yet.
	 */
	public function register() {


		$dependencies = [
			'jquery',
			'imagesloaded',
			'wp-js-hooks',
		];

		// @TODO: Should this be here?
		$gallery = phort_get_option( 'popup_gallery' );
		if ( 'lightgallery' === $gallery ) {
			$dependencies[] = 'phort-gallery-lightgallery';
		}
		if ( 'photoswipe' === $gallery ) {
			$dependencies[] = 'photoswipe-ui-default';
			$dependencies[] = 'photoswipe';
		}
		// Styles
		wp_register_style( 'phort-style', $this->build_directory_url . 'photography-portfolio.css' );
		wp_register_style( 'phort-gallery-lightgallery', $this->build_directory_url . 'libs/lightgallery.css' );
		wp_register_style( 'photoswipe-default-skin', $this->build_directory_url . 'libs/photoswipe-ui.css', NULL, '4.1.2' );
		wp_register_style( 'photoswipe', $this->build_directory_url . 'libs/photoswipe.css', [ 'photoswipe-default-skin' ], '4.1.2' );


		// Gallery Scripts
		wp_register_script( 'phort-gallery-lightgallery', $this->build_directory_url . 'libs/light-gallery-custom.js', [ 'jquery' ], NULL, true );
		wp_register_script( 'photoswipe-ui-default', $this->build_directory_url . 'libs/photoswipe-ui.js', NULL, '4.1.2', true );
		wp_register_script(
			'photoswipe',
			$this->build_directory_url . 'libs/photoswipe.js',
			[ 'jquery', 'photoswipe-ui-default' ],
			'4.1.2',
			true
		);

		// Scripts
		wp_register_script( 'wp-js-hooks', $this->build_directory_url . 'libs/wp-js-hooks.js', NULL, '1.0.0', true );
		wp_register_script( 'phort-app', $this->build_directory_url . 'photography-portfolio.js', $dependencies, CLM_VERSION, true );


		// Pass options to JavaScript side
		wp_localize_script( 'phort-app', '__phort', $this->javascript_settings() );
	}


	/**
	 * Return JavaScript settings to be included in a global
	 * @return mixed|void
	 */
	public function javascript_settings() {


		$gallery = phort_get_option( 'popup_gallery' );


		$settings = [
			'popup_gallery' => $gallery,
			'i18n'          => [
				'photoswipe' => [
					'facebook'  => esc_html__( 'Share on Facebook', 'photography-portfolio' ),
					'twitter'   => esc_html__( 'Tweet', 'photography-portfolio' ),
					'pinterest' => esc_html__( 'Pin it', 'photography-portfolio' ),
				],
			],
		];

		lightGallery::register();

		return apply_filters( 'phort/js/__phort', $settings );
	}


	public function detect_javascript() {

		echo "<script>document.documentElement.classList.add('js');</script>";
	}


	/**
	 * Adjust CSS Classes on the <body> element
	 *
	 * @filter body_class
	 *
	 * @param $classes
	 *
	 * @return array
	 */
	public function adjust_body_class( $classes ) {

		if ( ! phort_is_portfolio() ) {
			return $classes;
		}

		// If this is portfolio, add core portfolio class
		$classes[] = 'PP_Portfolio';


		// Single Portfolio
		if ( phort_is_single() ) {

			$classes[] = 'PP_Single';
			$classes[] = 'PP_Single--' . phort_slug_single();

			$gallery_type = phort_get_option( 'popup_gallery' );

			if ( 'disabled' !== $gallery_type && ! empty( $gallery_type ) ) {
				$classes[] = 'PP_Popup--' . sanitize_html_class( $gallery_type );
			}

		}

		// Portfolio Archive & Categories
		if ( phort_is_archive() ) {
			$classes[] = 'PP_Archive';
			$classes[] = 'PP_Archive--' . phort_slug_archive();
		}


		return $classes;
	}


	/**
	 * If there is no theme support, users can add their own class name to the wrapper.
	 * Attach that classname with this funciton:
	 *
	 * @param $classes
	 * @param $class
	 *
	 * @return array
	 */
	public function adjust_wrapper_class( $classes, $class ) {

		// Only affect .PP_Wrapper
		if ( ! in_array( 'PP_Wrapper', $classes ) ) {
			return $classes;
		}

		$custom_classes = phort_get_option( 'wrapper_class' );

		if ( $custom_classes ) {
			$classes = array_merge( $classes, phort_get_class( $custom_classes ) );
		}

		return $classes;

	}


	// @TODO: Should this be here?
	public function display_photoswipe_html() {

		phort_get_template( 'partials/photoswipe' );
	}

}