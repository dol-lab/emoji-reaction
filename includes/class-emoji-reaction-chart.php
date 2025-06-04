<?php

/**
 * The chart functionality of the plugin.
 *
 * @since      0.4.0
 *
 * @package    Emoji_Reaction
 * @subpackage Emoji_Reaction/includes
 */

/**
 * The chart functionality class.
 *
 * Handles shortcode and Gutenberg block for displaying reaction charts.
 *
 * @since      0.4.0
 * @package    Emoji_Reaction
 * @subpackage Emoji_Reaction/includes
 */
class Emoji_Reaction_Chart {

	/**
	 * The ID of this plugin.
	 *
	 * @since    0.4.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    0.4.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * The meta key to get the reactions from post meta.
	 *
	 * @since    0.4.0
	 * @access   private
	 * @var      string    $meta_key    The meta key.
	 */
	private $meta_key;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    0.4.0
	 * @param    string $plugin_name The name of the plugin.
	 * @param    string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->meta_key    = '_' . $plugin_name . '_likes';

		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'wp_ajax_emoji_reaction_chart_data', array( $this, 'get_chart_data_ajax' ) );
		add_action( 'wp_ajax_nopriv_emoji_reaction_chart_data', array( $this, 'get_chart_data_ajax' ) );
	}

	/**
	 * Register the shortcode.
	 *
	 * @since 0.4.0
	 */
	public function register_shortcode() {
		add_shortcode( 'emoji_reaction_chart', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Register the Gutenberg block.
	 *
	 * @since 0.4.0
	 */
	public function register_block() {
		if ( function_exists( 'register_block_type' ) ) {
			wp_register_script(
				'emoji-reaction-chart-block',
				plugin_dir_url( __DIR__ ) . 'public/js/emoji-reaction-chart-block.js',
				array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components' ),
				$this->version
			);

			register_block_type(
				'emoji-reaction/chart',
				array(
					'editor_script'   => 'emoji-reaction-chart-block',
					'render_callback' => array( $this, 'render_block' ),
					'attributes'      => array(
						'type'    => array(
							'type'    => 'string',
							'default' => 'donut',
						),
						'post_id' => array(
							'type'    => 'number',
							'default' => 0,
						),
					),
				)
			);
		}
	}

	/**
	 * Render the shortcode.
	 *
	 * @since 0.4.0
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'type'    => 'donut',
				'post_id' => get_the_ID(),
			),
			$atts,
			'emoji_reaction_chart'
		);

		return $this->render_chart( $atts );
	}

	/**
	 * Render the Gutenberg block.
	 *
	 * @since 0.4.0
	 *
	 * @param array $attributes Block attributes.
	 * @return string HTML output.
	 */
	public function render_block( $attributes ) {
		$attributes = wp_parse_args(
			$attributes,
			array(
				'type'    => 'bar',
				'post_id' => get_the_ID(),
			)
		);

		return $this->render_chart( $attributes );
	}

	/**
	 * Render the chart HTML.
	 *
	 * @since 0.4.0
	 *
	 * @param array $args Chart arguments.
	 * @return string HTML output.
	 */
	private function render_chart( $args ) {
		$post_id = intval( $args['post_id'] );
		$type    = sanitize_text_field( $args['type'] );
		if ( ! in_array( $type, array( 'bar', 'donut' ), true ) ) {
			$type = 'bar';
		}

		if ( $post_id <= 0 ) {
			$post_id = get_the_ID();
		}

		if ( ! $post_id ) {
			return '<p>' . esc_html__( 'No post ID available for chart.', 'emoji-reaction' ) . '</p>';
		}

		$chart_id     = 'emoji-reaction-chart-' . $post_id . '-' . wp_rand( 1000, 9999 );
		$loading_text = esc_html__( 'Loading chart...', 'emoji-reaction' );
		$refresh_attr = esc_attr__( 'Refresh chart', 'emoji-reaction' );
		$e            = fn( $e, ...$f )=>call_user_func( $e, ...$f );

		$html = "
			<div
				class='emoji-reaction-chart-container'
				style='height: 250px;'
				data-chart-id='{$e('esc_attr', $chart_id)}'
				data-post-id='{$e('intval', $post_id)}'
				data-type='{$e('esc_js', $type)}'
				data-loading='false'
				data-clicked='false'
			>
				<div class='emoji-reaction-chart-header'>
					<button class='emoji-reaction-chart-refresh'
						onclick=\"emojiReactionChart.refresh(this.closest('.emoji-reaction-chart-container'))\"
						title='$refresh_attr'>
					<i class='fa fa-refresh' aria-hidden='true'></i>
					</button>
				</div>
				<canvas id='{$e('esc_attr', $chart_id)}'></canvas>
					<div class='emoji-reaction-chart-loading'>
				$loading_text
				</div>
				<script>
					(() => {
						const parentOfScript = document.currentScript?.parentNode;
						setTimeout(() => {
							document.addEventListener('DOMContentLoaded', () => emojiReactionChart.init(parentOfScript) );
						}, 1);
					})()
				</script>
			</div>
			";
		return $html;
	}

	/**
	 * AJAX handler for getting chart data.
	 *
	 * @since 0.4.0
	 */
	public function get_chart_data_ajax() {
		$post_id = isset( $_GET['post_id'] ) ? intval( $_GET['post_id'] ) : 0;

		if ( $post_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid post ID.' ) );
			return;
		}

		// Check if post exists and is publicly viewable
		$post = get_post( $post_id );
		if ( ! $post || ! is_post_publicly_viewable( $post ) ) {
			wp_send_json_error( array( 'message' => 'Post not found or not accessible.' ) );
			return;
		}

		$chart_data = $this->get_chart_data( $post_id );
		wp_send_json_success( $chart_data );
	}

	/**
	 * Get chart data for a post.
	 *
	 * @since 0.4.0
	 *
	 * @param int $post_id Post ID.
	 * @return array Chart data.
	 */
	private function get_chart_data( $post_id ) {
		$reactions = get_post_meta( $post_id, $this->meta_key, true );
		$emojis    = apply_filters( 'emoji_reaction_emojis', Emoji_Reaction::get_default_emojis() );

		$labels            = array();
		$data              = array();
		$background_colors = array();
		$border_colors     = array();

		// Default colors for charts
		$colors = array(
			array(
				'bg'     => 'rgba(54, 162, 235, 0.2)',
				'border' => 'rgba(54, 162, 235, 1)',
			),
			array(
				'bg'     => 'rgba(255, 99, 132, 0.2)',
				'border' => 'rgba(255, 99, 132, 1)',
			),
			array(
				'bg'     => 'rgba(255, 205, 86, 0.2)',
				'border' => 'rgba(255, 205, 86, 1)',
			),
			array(
				'bg'     => 'rgba(75, 192, 192, 0.2)',
				'border' => 'rgba(75, 192, 192, 1)',
			),
			array(
				'bg'     => 'rgba(153, 102, 255, 0.2)',
				'border' => 'rgba(153, 102, 255, 1)',
			),
			array(
				'bg'     => 'rgba(255, 159, 64, 0.2)',
				'border' => 'rgba(255, 159, 64, 1)',
			),
		);

		$color_index   = 0;
		$has_reactions = false;
		$emoji_data    = array();

		// First, collect all emoji data with counts
		foreach ( $emojis as $emoji ) {
			$emoji_unicode = $emoji[0];
			$emoji_name    = $emoji[1];

			// Only collect emojis that have votes
			if ( ! empty( $reactions ) && isset( $reactions[ $emoji_unicode ] ) ) {
				$count = count( $reactions[ $emoji_unicode ] );
				if ( $count > 0 ) {
					$emoji_data[]  = array(
						'unicode' => $emoji_unicode,
						'name'    => $emoji_name,
						'count'   => $count,
					);
					$has_reactions = true;
				}
			}
		}

		// Sort emoji data by count in descending order (highest first)
		if ( $has_reactions ) {
			usort(
				$emoji_data,
				function ( $a, $b ) {
					return $b['count'] - $a['count'];
				}
			);

			// Now build the chart arrays in sorted order
			foreach ( $emoji_data as $emoji_item ) {
				$labels[] = $emoji_item['unicode'] . ' ' . ucfirst( $emoji_item['name'] );
				$data[]   = $emoji_item['count'];

				$color               = $colors[ $color_index % count( $colors ) ];
				$background_colors[] = $color['bg'];
				$border_colors[]     = $color['border'];

				++$color_index;
			}
		}

		// If no reactions exist, return data indicating no reactions
		if ( ! $has_reactions ) {
			return array(
				'labels'       => array(),
				'datasets'     => array(),
				'no_reactions' => true,
				'message'      => __( 'No reactions yet', 'emoji-reaction' ),
			);
		}

		return array(
			'labels'       => $labels,
			'datasets'     => array(
				array(
					'label'           => __( 'Reactions', 'emoji-reaction' ),
					'data'            => $data,
					'backgroundColor' => $background_colors,
					'borderColor'     => $border_colors,
					'borderWidth'     => 1,
				),
			),
			'no_reactions' => false,
		);
	}
}
