<?php

/**
    Function to show number of view for each post
**/

function show_PostViews($post_ID){


    $count_key = 'post_views_count';
    $count = get_post_meta($post_ID,$count_key,true);
    if ($count != '') {
        $count++;
        if ($count == '1') {
            return $count . ' Vue';
        }
        else{
            return $count . ' Vues';
        }
    }
}
 


/**
    Function to update the numbers of views
**/
function update_PostViews($post_ID){


    $count_key = 'post_views_count';
    $count = get_post_meta($post_ID,$count_key,true);
    if ($count == '') {
        $count = 0;
        delete_post_meta($post_ID,$count_key);
        add_post_meta($post_ID,$count_key,'0');
    }
    else{
        $count++;
        update_post_meta($post_ID, $count_key, $count);
    }
}

?>
