<?php
/**
 * Plugin Name:       Oxygen Translations
 * Description:       A plugin for translating text in Oxygen
 * Version:           1.0.0
 * Requires at least: 5.2
 * Author:            Max Don
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

// require_once PLUGIN_PATH . "cpts.php";

add_action( 'init', function () {
    /**
     * Post Type: Oxygen Templates.
     */

    
    // require oxygen
    if ( ! is_plugin_active( 'oxygen3/functions.php' ) ) {
        error_log('oxygen3 not activated');
        return;
    }
    
    // require polylang
    if ( ! is_plugin_active( 'polylang/polylang.php' ) ) {
        error_log('polylang not activated');
        return;
    }

    // var_dump( get_option( 'active_plugins', array() ), true );
    // die();
    // $switcher = new PLL_Switcher();
	// $list = $switcher->the_languages( PLL()->links, [ 'raw'=>1 ] );
    // var_dump( $list );

    // var_dump( pll_languages_list() );
    // die();

    // require acf
    if ( ! is_plugin_active( 'advanced-custom-fields-pro/acf.php' ) && ! is_plugin_active( 'advanced-custom-fields/acf.php' ) ) {
        error_log('advanced-custom-fields not activated');
        return;
    }

    $labels = [
        "name" => __( "Oxygen Templates", "custom-post-type-ui" ),
        "singular_name" => __( "Oxygen Template", "custom-post-type-ui" ),
    ];

    $args = [
        "label" => __( "Oxygen Templates", "custom-post-type-ui" ),
        "labels" => $labels,
        "description" => "",
        "public" => true,
        "publicly_queryable" => true,
        "show_ui" => true,
        "show_in_rest" => false,
        "rest_base" => "",
        "has_archive" => true,
        "show_in_menu" => true,
        "show_in_nav_menus" => true,
        "delete_with_user" => false,
        "exclude_from_search" => false,
        "capability_type" => "post",
        "map_meta_cap" => true,
        "hierarchical" => false,
        "rewrite" => ["slug" => "oxygentemplates", "with_front" => true],
        "query_var" => true,
        "supports" => ["title", "editor", "thumbnail"],
    ];

    register_post_type( "oxygentemplates", $args );

    /**
     * Post Type: Oxygen Texts.
     */

    $labels = [
        "name" => __( "Oxygen Texts", "custom-post-type-ui" ),
        "singular_name" => __( "Oxygen Text", "custom-post-type-ui" ),
    ];

    register_post_type( "oxygentext", [
        "label" => __( "Oxygen Texts", "custom-post-type-ui" ),
        "labels" => $labels,
        "description" => "",
        "public" => true,
        "publicly_queryable" => true,
        "show_ui" => true,
        "show_in_rest" => true,
        "rest_base" => "",
        "rest_controller_class" => "WP_REST_Posts_Controller",
        "has_archive" => false,
        "show_in_menu" => true,
        "show_in_nav_menus" => true,
        "delete_with_user" => false,
        "exclude_from_search" => false,
        "capability_type" => "post",
        "map_meta_cap" => true,
        "hierarchical" => false,
        "rewrite" => ["slug" => "oxygentext", "with_front" => true],
        "query_var" => true,
        "supports" => ["title"],
    ] );

    // TODO: get polylang languages
    // https://polylang.wordpress.com/documentation/documentation-for-developers/functions-reference/

    $langFields = [];

    global $wpdb;

    $result = $wpdb->get_results ( "
        SELECT term_id, name, slug 
        FROM wp_terms 
        WHERE term_id IN (
                            SELECT term_taxonomy_id 
                            FROM wp_term_taxonomy 
                            WHERE taxonomy='language'
                        );
    " );

    foreach ( $result as $lang )
    {
        $langFields[] = array(
            'key' => 'field_' . 'acf' . $lang->slug . $lang->term_id,
            'label' => $lang->name,
            'name' => $lang->slug,
            'type' => 'text',
            'instructions' => '',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => array(
                'width' => '',
                'class' => '',
                'id' => '',
            ),
            'default_value' => '',
            'placeholder' => '',
            'prepend' => '',
            'append' => '',
            'maxlength' => '',
        );
    }

    acf_add_local_field_group( array(
        'key' => 'group_5f159c125d1dc',
        'title' => 'Oxygen Texts',
        'fields' => $langFields,
        'location' => array(
            array(
                array(
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'oxygentext',
                ),
            ),
        ),
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'hide_on_screen' => array(
            0 => 'the_content',
            1 => 'excerpt',
            2 => 'discussion',
            3 => 'comments',
            4 => 'revisions',
            5 => 'slug',
            6 => 'author',
            7 => 'format',
            8 => 'page_attributes',
            9 => 'featured_image',
            10 => 'categories',
            11 => 'tags',
            12 => 'send-trackbacks',
        ),
        'active' => true,
        'description' => '',
    ) );

    add_shortcode( 'template', function ( $atts = [], $content = null ) {
        if ( empty( $atts['name'] ) ) {
            return 'The parameter "name" is required!';
        }

        $name = $atts['name'];

        global $wpdb;

        $template_id = $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT ID
                FROM $wpdb->posts
                WHERE post_title = %s
                    AND post_type = 'oxygentemplates'
                ",
                $name
            )
        );

        if ( empty( $template_id ) ) {
            return 'The template named "' . $name . '" doesn\'t exist';
        }

        $template_meta = get_post_meta( $template_id );

        $texts_titles = [];

        preg_match_all( '/(?<=\*\|)(.*)(?=\|\*)/U', $template_meta['ct_builder_shortcodes'][0], $texts_titles );

        if ( empty( $texts_titles ) ) {
            return 'There are no designations in the provided template, please make sure that you are using *| and |*';
        }

        $texts_titles_replacements = [];

        $locale = explode( "_", get_locale() )[0];

        if ( empty( $locale ) ) {
            return 'Something is wrong with the locale, report to admin.';
        }

        for ( $i = 0; $i < count( $texts_titles[0] ); $i++ ) {
            $texts_id_temp = $wpdb->get_var(
                $wpdb->prepare(
                    "
                    SELECT ID
                    FROM $wpdb->posts
                    WHERE post_title = %s
                        AND post_type = 'oxygentext'
                    ",
                    $texts_titles[0][$i]
                )
            );

            $texts_meta = get_post_meta( $texts_id_temp );

            $text = $texts_meta[$locale];

            if ( empty( $text ) ) {
                $texts_titles_replacements[$texts_titles[0][$i]] = 'NO_TRANSLATION_AVAILABLE';
            } else {
                $texts_titles_replacements[$texts_titles[0][$i]] = $text;
            }
        }

        $result = do_shortcode( $template_meta['ct_builder_shortcodes'][0] );

        foreach ( $texts_titles_replacements as $key => $value ) {
            $result = preg_replace( '/\*\|' . $key . '\|\*/U', $value[0], $result );
        }

        if ( empty( $result ) ) {
            return '';
        }

        return $result;
    } );


}, 10000 );
