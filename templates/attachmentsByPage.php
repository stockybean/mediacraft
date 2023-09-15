<a class="button button-warning" id="show-only-issues" style="margin-top: 1rem;">Hide Images If They Contain No Issues</a>
<div class="wrapper">
    <?php foreach($pages as $page) : ?>
    <?php $images = apply_filters( 'responsive_picture_analysis', $page->post_content ); ?>
    <?php if( empty($images) ) continue; ?>
    <div class="wrapper-card">
        <div class="sort-by-page">
            <h2 style="display: flex; align-items: center; margin-top: 0;">
                <span style="margin-right: 0.5rem;">
                    <?php echo $page->post_title; ?></span>
                <a class="button" style="margin-right: 0.5rem;" href="<?php echo get_permalink($page); ?>" title="Go to this page" target="_blank">Go to page</a>
                <a class="button" href="<?php echo get_permalink($page); ?>?lc_action_launch_editing=1" title="Go to this page" target="_blank">Edit in Frontend</a>
            </h2>
            <?php foreach($images as $image) : ?>
            <?php echo $image; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>