<?php
add_shortcode('imagepress-feed', 'imagepress_feed');

function imagepress_feed() {
    global $wpdb;

    $user_ID = get_current_user_id();

    $myFollowing = array(pwuf_get_following($user_ID));
    $myFollowing = array_unique($myFollowing);

    $followers = '';
    if (!empty($myFollowing[0])) {
        $followers = implode(',', $myFollowing[0]);
        $followers = "AND userID IN (" . $followers . ")";
    }
    ?>

    <div class="feed-actions" style="display:none;">
        <div class="feed-action-following">Following</div>
        <div class="feed-action-discover feed-action-inactive hint hint--right" data-hint="Coming soon">Discover</div>
    </div>

    <div class="feed-container">
        <div id="feed-data">
            <?php
            // Feed loop
            $res = $wpdb->get_results($wpdb->prepare("SELECT
                ID,
                userID,
                postID,
                postKeyID,
                actionType,
                actionTime,
                status
            FROM {$wpdb->prefix}notifications
            WHERE actionType = 'added' %s
                AND ID < %d
                ORDER BY ID DESC LIMIT 20", $followers, $last_id));

            include 'feed-data.php';
            ?>
        </div>
        <div class="ajax-load feed-loading" style="display: none;"></div>

        <script>
        jQuery(window).scroll(function() {
            if (jQuery(window).scrollTop() + jQuery(window).height() >= (jQuery(document).height()) - 128) {
                var last_id = jQuery(".feed-item:last-child").attr("data-id");

                loadMoreData(last_id);
            }
        });

        function loadMoreData(last_id) {
            jQuery.ajax({
                url: '<?php echo plugins_url('loadMoreData.php', __FILE__); ?>?last_id=' + last_id,
                type: "get",
                beforeSend: function() {
                    jQuery('.ajax-load').show();
                }
            }).done(function(data) {
                jQuery('.ajax-load').hide();
                jQuery("#feed-data").append(data);

                var duplicateChk = {};

                jQuery('.feed-item').each(function() {
                    if (duplicateChk.hasOwnProperty(this.id)) {
                        jQuery(this).remove();
                    } else {
                        duplicateChk[this.id] = 'true';
                    }
                });
            }).fail(function(jqXHR, ajaxOptions, thrownError) {
                // console.log('Feed not responding...');
            });
        }
        </script>
    </div>
    <?php
}
