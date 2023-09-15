<div class="wrapper">
    <div style="display: flex; align-items: baseline; margin-bottom: 1rem;">
        <button id="bulk-check" class="button button-primary button-large" style="margin-right: 0.5rem;">Scan Sizes &amp; Retina</button>
        <button id="show-valid" class="button button-primary button-large">Show/Hide Valid Images</button>
    </div>
    <div class="wrapper-card">
        <?php while ( $attachments->have_posts() ) : $attachments->the_post(); $attachment = $attachments->post; ?>
            <div class="col" data-qualified="<?php echo 1; ?>">
                <figure style="width: 4rem; margin: 0; padding-right: 0.5rem;text-align: center;">
                    <img style="width: 100%; height: auto;" src="<?php echo wp_get_attachment_image_url( $attachment->ID, 'thumbnail' ); ?>" />
                    <span style="color: #a5a5a5; font-size: 0.75rem;"><?php echo $attachment->ID; ?></span>
                </figure>
                <div style="max-width: margin-right: 0.5rem;">
                    <p style="word-break: break-all; margin: 0 0 0.25rem;">
                        <?php echo $attachment->post_title; ?>
                    </p>
                </div>
                <div style="display: grid; grid-gap: 3px; margin-left: auto;">
                    <?php
                        $this->renderCropButton($attachment);
                        $this->renderCheckSizesButton($attachment);
                        $this->renderRegenerateButton($attachment);
                    ?>
                </div>
            </div>
        <?php endwhile; ?>
    </div>

    <div class="pagination">
        <?php echo paginate_links( array(
            'base'         => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
            'total'        => $attachments->max_num_pages,
            'current'      => max( 1, get_query_var( 'paged' ) ),
            'format'       => '?paged=%#%',
            'show_all'     => false,
            'type'         => 'plain',
            'end_size'     => 2,
            'mid_size'     => 1,
            'prev_next'    => true,
            'prev_text'    => sprintf( '<i></i> %1$s', __( 'Newer attachments', 'mediacraft' ) ),
            'next_text'    => sprintf( '%1$s <i></i>', __( 'Older attachments', 'mediacraft' ) ),
            'add_args'     => false,
            'add_fragment' => '',
        )); ?>
    </div>
</div>