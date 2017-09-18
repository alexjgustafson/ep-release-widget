<?php
/**
 * Plugin Name: Episode Release Date Widget
 * Plugin URI: https://github.com/alexjgustafson/ep-release-widget
 * Description: Tells your visitors when they can expect the next episode
 * Version: 0.9
 * Author: alexjgustafson
 * Author URI: https://alexjgustafson.blog/
 * License: GPL 2.0
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text domain: episode-release-widget
 * Domain Path: /languages 
 */

if ( !class_exists( 'EpisodeReleaseDateWidget' ) ){

	class EpisodeReleaseDateWidget extends WP_Widget{

		//Setup the widget class

		public function __construct(){
			$widget_ops = array(
				'classname' => 'episode_release_widget' ,
				'description' => 'Tells your visitors when they can expect the next episode'
			);

			parent::__construct('episode_release_widget', 'Episode Release Widget', $widget_ops);
		}


		// The widget options form in admin

		public function form($instance){
			$instance = wp_parse_args( (array) $instance, array( 'title' => '') );
			$title = sanitize_text_field($instance['title']);
			$date_value = isset( $instance['custom_release_date'] ) ? $instance['custom_release_date'] : "";
			$recur_option = isset( $instance['recurring_release_schedule'] ) ? $instance['recurring_release_schedule'] : "";
			$podcast_category_option = isset( $instance['podcast_category'] ) ? $instance['podcast_category'] : "";
			$categories = get_categories();
			?>

			<p>
				<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e( 'Title:' ); ?></label>
				<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
			</p>
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'custom_release_date') ); ?>"><?php _e( 'Custom release date:' ); ?></label>
				<input type="date" value="<?php echo esc_attr( $date_value ); ?>" name="<?php echo esc_attr($this->get_field_name( 'custom_release_date') ); ?>" id="<?php echo esc_attr($this->get_field_id( 'custom_release_date' ) ); ?>" />
			</p>
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'recurring_release_schedule' ) ); ?>"><?php _e( 'Recurring schedule:' ); ?></label>
				<select name="<?php echo esc_attr( $this->get_field_name( 'recurring_release_schedule' ) ); ?>">
					<option value=""><?php _e( '&mdash; Select &mdash;' ); ?></option>
					<option value='1 week' <?php selected( $recur_option , '1 week'); ?> > <?php _e( 'Weekly' );?> </option>
					<option value='2 weeks' <?php selected( $recur_option , '2 weeks'); ?> > <?php _e( 'Every 2 weeks' );?></option>
					<option value='4 weeks' <?php selected( $recur_option , '4 weeks'); ?> > <?php _e( 'Every 4 weeks' );?></option>
					<option value='1 month' <?php selected( $recur_option , '1 month'); ?> > <?php _e( 'Monthly' );?></option>
				</select>
			</p>
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'podcast_category' ) ); ?>"><?php _e( 'Podcast category:' ); ?></label>
				<select id="<?php echo esc_attr( $this->get_field_id( 'podcast_category') ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'podcast_category' ) ); ?>">
					<option value=""><?php _e( '&mdash; Select &mdash;' ); ?></option>
					<?php foreach ($categories as $value) : ?>
						<option value="<?php echo esc_attr( strtolower( $value->cat_name) ); ?>" <?php selected( $podcast_category_option , strtolower($value->cat_name) ); ?> >
							<?php echo esc_attr( $value->cat_name);?>
						</option>
					<?php endforeach ?>
				</select>
			</p>
			<?php
		}


		// Outputs the content of the widget

		public function widget($args , $instance){
			$cat = $instance['podcast_category'];
			$the_last_episode = $this->get_last_episode($cat);
			$the_last_episode = $the_last_episode[0];


			/** This filter is documented in core wp-includes/widgets/class-wp-widget-pages.php */
			$title = apply_filters( 'widget_title' , empty( $instance['title'] ) ? __( 'Episode Release Date' ) : $instance['title'], $instance, $this->id_base);


			echo $args['before_widget'];

			if ( $title ){
				echo $args['before_title'] . $title . $args['after_title'];
			}

			printf(
				/*translators: 1: URL of the podcast episode , 2:title of the podcast episode, 3: date of the podcast episode */
				__('Last episode: <a href=" %1$s "> %2$s on %3$s </a> '), 
				$the_last_episode->guid,
				$the_last_episode->post_title,
				substr($the_last_episode->post_date, 0, 10)
			);
			echo "<br/>";

			if(! empty( $instance['custom_release_date'] )){
				printf(
					__('Next episode: %s'),
					$instance['custom_release_date']
				);
			} else{
				printf(
					__('Next episode: %s'),
					$this->next_episode_recurring($instance['recurring_release_schedule'], $the_last_episode)
				);
			}

			echo $args['after_widget'];

		}


		// Processing widget options on save

		public function update( $new_instance, $old_instance ) {
			$instance = $old_instance;
			$instance['title'] = sanitize_text_field( $new_instance['title'] );
			$instance['custom_release_date'] = $new_instance['custom_release_date'];
			$instance['recurring_release_schedule'] = $new_instance['recurring_release_schedule'];
			$instance['podcast_category'] = $new_instance['podcast_category'];
			
			return $instance;
		}


		// Get the last episode released

		public function get_last_episode($cat){
				$args = array(
					'numberposts' => 1,
					'category_name' => $cat,
				);

		return get_posts($args);	
		}


		// Calculate next episode's release date

		public function next_episode_recurring($recurrance , $last_ep){
			if($recurrance === ""){
				return "To Be Determined";
			} else {
				$date = $last_ep->post_date;
				$date = substr($date, 0, 10);
				$date = strtotime($date);
				$date = date('Y-m-d', $date);
				$date = date_create($date);
				$date = date_add($date , date_interval_create_from_date_string($recurrance));
				$date = date_format($date, 'Y-m-d');

				return $date;
			}
		}	
	}

	add_action('widgets_init', function(){
		register_widget('EpisodeReleaseDateWidget');	
	});
}
