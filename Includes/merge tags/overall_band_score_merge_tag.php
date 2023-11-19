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

