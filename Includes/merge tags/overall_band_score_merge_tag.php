<?php
function add_overall_band_score_merge_tag( $merge_tags, $form_id, $fields, $element_id ) {
    $merge_tags[] = array(
        'label' => 'Overall Band Score',
        'tag'   => '{overall_band_score:1,2,3}'
    );

    return $merge_tags;
}

add_filter( 'gform_custom_merge_tags', 'add_overall_band_score_merge_tag', 10, 4 );

function replace_overall_band_score_merge_tag( $text, $form, $entry, $url_encode, $esc_html, $nl2br, $format ) {
    if ( preg_match_all( '/\{overall_band_score:([^\}]*)\}/', $text, $matches, PREG_SET_ORDER ) ) {
        foreach ( $matches as $match ) {
            // Extract field IDs
            $field_ids = explode( ',', $match[1] );
            $total_score = 0;
            $score_count = 0;

            foreach ( $field_ids as $field_id ) {
                $field_value = rgar( $entry, $field_id );

                // Extract the first number from the field value
                if ( preg_match( '/\d+/', $field_value, $number_matches ) ) {
                    $total_score += floatval( $number_matches[0] );
                    $score_count++;
                }
            }

            if ( $score_count > 0 ) {
                $mean_score = $total_score / $score_count;
                $rounded_score = floor( $mean_score * 2 ) / 2; // Round down to nearest 0.5
                $text = str_replace( $match[0], $rounded_score, $text );
            } else {
                $text = str_replace( $match[0], '', $text ); // Replace with empty string if no scores
            }
        }
    }

    return $text;
}

add_filter( 'gform_replace_merge_tags', 'replace_overall_band_score_merge_tag', 10, 7 );

function schedule_band_score_tag_update( $post_id, $post, $update ) {
    // Schedule the tag update to occur 1 minute after the post is saved
    wp_schedule_single_event( time() + 60, 'update_band_score_tag', array( $post_id ) );
}
add_action( 'wp_insert_post', 'schedule_band_score_tag_update', 10, 3 );

function update_band_score_tag( $post_id ) {
    // Get the value of the custom field 'Overall_Band_Score'.
    $band_score = get_post_meta( $post_id, 'Overall_Band_Score', true );

    // Check if the custom field is set and not empty.
    if ( !empty( $band_score ) ) {
        $tag = 'Band ' . $band_score;

        // Add or update the tag in the post.
        wp_set_post_tags( $post_id, $tag, true );
    }
}
add_action( 'update_band_score_tag', 'update_band_score_tag' );
