<?php
/**
 * @author ProudCity
 */

use Proud\Core;

if ( class_exists( 'RGFormsModel' ) ) {
	class GravityForm extends Core\ProudWidget {

		function __construct() {
			parent::__construct(
				'proud_gravity_form', // Base ID
				__( 'Form', 'wp-proud-core' ), // Name
				array( 'description' => __( 'ProudCity Forms', 'wp-proud-core' ), ) // Args
			);
		}

		function initialize() {
			$forms   = \RGFormsModel::get_forms( 1, 'title' );
			$options = [];
			$default = 0;
			foreach ( $forms as $form ) {
				if ( ! $default ) {
					$default = $form->id;
				}
				$options[ $form->id ] = $form->title;
			}
			$this->settings = [
				'form_id'  => [
					'#title'          => 'Gravity form ID',
					'#type'           => 'select',
					'#default_value'  => $default,
					'#description'    => 'The gravity form ID to be printed in the dropdown',
					'#to_js_settings' => false,
					'#options'        => $options
				],
				'dropdown' => [
					'#title'         => 'Display as dropdown?',
					'#type'          => 'checkbox',
					'#description'   => 'Display as dropdown?',
					'#return_value'  => '1',
					'#label_above'   => true,
					'#replace_title' => 'Yes',
					'#default_value' => false
				]
			];
		}


		/**
		 * Determines if content empty, show widget, title ect?
		 *
		 * @see self::widget()
		 *
		 * @param array $args Widget arguments.
		 * @param array $instance Saved values from database.
		 *
		 * @return boolean
		 */
		public function hasContent( $args, &$instance ) {
			if ( ! empty( $instance['form_id'] ) ) {
				$instance['shortcode'] = '[gravityform id="' . $instance['form_id'] . '" title="false" description="false"]';
				// If we're in the loop (content), let form be a shortcode
				if ( in_the_loop() ) {
					$instance['form'] = $instance['shortcode'];
				} // outside the loop (sidebar ect), so render
				else {
					$instance['form'] = Core\sanitize_input_text_output( $instance['shortcode'], true );
				}

				return true;
			}

			return false;
		}

		/**
		 * Front-end display of widget.
		 *
		 * @see WP_Widget::widget()
		 *
		 * @param array $args Widget arguments.
		 * @param array $instance Saved values from database.
		 */
		public function printWidget( $args, $instance ) {
			extract( $instance );
			// Displaying as dropdown
			if ( ! empty( $dropdown ) ) {
				?>
          <a href="#" id="sub-dropdown" data-toggle="dropdown"><i aria-hidden="true" class="fa fa-fw fa-envelope"></i>Subscribe
            <!--<span class="caret"></span>--></a>
            <ul class="dropdown-menu nav nav-pills" aria-labelledby="sub-dropdown">
              <li style="padding: 10px 15px;"><?php echo $form ?></li>
            </ul>
				<?php
			} // Normal
			else {
				echo $form;
			}
		}
	}

	// register Foo_Widget widget
	function register_gravity_form_widget() {
    register_widget( 'GravityForm' );
	}

	add_action( 'widgets_init', 'register_gravity_form_widget' );
}