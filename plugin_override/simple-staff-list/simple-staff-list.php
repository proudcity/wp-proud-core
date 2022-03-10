<?php

class Proud_Simple_Staff_List{

	private static $instance;

	/**
	 * Spins up the instance of the plugin so that we don't get many instances running at once
	 *
	 * @since 1.0
	 * @author SFNdesign, Curtis McHale
	 *
	 * @uses $instance->init()                      The main get it running function
	 */
	public static function instance(){

		if ( ! self::$instance ){
			self::$instance = new Proud_Simple_Staff_List();
			self::$instance->init();
		}

	} // instance

	/**
	 * Spins up all the actions/filters in the plugin to really get the engine running
	 *
	 * @since 1.0
	 * @author SFNdesign, Curtis McHale
	 */
	public function init(){

        add_action( 'sslp_after_staff_member_admin_fields', array( $this, 'proud_staff_fields' ) );
        add_action( 'sslp_save_staff_member_details', array( $this, 'proud_save_extra_staff_fields' ), 10, 2 );

	} // init

    /**
     * Adds extra fields to Simple Staff List Metabox
     *
     * @since 2022.03.10
     * @author Curtis McHale
     * 
     * @param       $post_id        int             required                    The ID of the post we're adding fields to
     * @uses        get_post_meta()                                             Returns post_meta given post_id and key
     * @uses        absint()                                                    no negative numbers
     * @uses        esc_url()                                                   escapes URL
     * @uses        wp_kses_post()                                              escapes and sanitizes post type content
     */
    public static function proud_staff_fields( $post_id ){

        $linkedin_link = get_post_meta( absint( $post_id ), '_proud_linkedin_link', true );
        ?>
            <label for="_proud_linkedin_link">
                Linkedin Link:
                <input type="url" name="_proud_linkedin_link" id="_proud_linkedin_link" placeholder="Linkedin Profile Link" value="<?php echo esc_url( $linkedin_link ); ?>" />
            </label>
        <?php
        $contact_link = get_post_meta( absint( $post_id ), '_proud_contact_link', true );
        ?>
            <label for="_proud_contact_link">
                Contact Link:
                <input type="url" name="_proud_contact_link" id="_proud_contact_link" placeholder="Contact Link" value="<?php echo esc_url( $contact_link ); ?>" />
            </label>
        <?php
            $address = get_post_meta( absint( $post_id ), '_proud_address', true );
        ?>
            <label for="_proud_address">Address:</label>
                <textarea name="_proud_address" id="_proud_address" placeholder="Contact Address"><? echo wp_kses_post( $address ); ?></textarea>
    <?php
    } // proud_staff_fields

    /**
     * Allows us to save fields added above
     * REMEMBER: we are responsible for sanitizing fields here as we save them
     * 
     * @since 2022.03.10
     * @author Curtis McHale
     * 
     * @param       $post_id        int             required                The ID of the post we're saving content for
     * @param       $post_fields    array           required                This is the passed $_POST value from the save screen
     * @uses        update_post_meta()                                      updates the post_meta given post_id and key
     * @uses        absint()                                                makes sure we don't have negative numbers
     * @uses        sanitize_url()                                          makes this text safe as a URL
     * @uses        wp_kses_post()                                          makes this content safe as post content
     */
    public static function proud_save_extra_staff_fields( $post_id, $post_fields ){

        if ( isset( $post_fields['_proud_linkedin_link'] ) ){
            update_post_meta( absint( $post_id ), '_proud_linkedin_link', sanitize_url( $post_fields['_proud_linkedin_link']) );
        }

        if ( isset( $post_fields['_proud_contact_link'] ) ){
            update_post_meta( absint( $post_id ), '_proud_contact_link', sanitize_url( $post_fields['_proud_contact_link']) );
        }

        if ( isset( $post_fields['_proud_address'] ) ){
            update_post_meta( absint( $post_id ), '_proud_address', wp_kses_post( $post_fields['_proud_address'] ) );
        }

    } // proud_save_extra_staff_fields

} // Proud_Simple_Staff_List

Proud_Simple_Staff_List::instance();