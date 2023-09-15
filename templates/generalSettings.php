<form id="general-settings-form" method="post" action="options.php">
    <?php settings_fields( 'media-optimization-group' ); ?>
    <?php do_settings_sections( 'media-optimization-group' ); ?>
    <table class="form-table">
        <tr valign="top">
            <th scope="row">Site Settings</th>
            <td>
                <input type="hidden" name="mediacraft_settings" value="<?php echo esc_attr( get_option('mediacraft_settings') ); ?>" />
                <div id="site_settings_editor" class="ace-editor"></div>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">Media Sizes</th>
            <td>
                <input type="hidden" name="media_optimization_sizes" value="<?php echo esc_attr( get_option('media_optimization_sizes') ); ?>" />
                <div id="media_sizes_editor" class="ace-editor"></div>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">Generate APIs</th>
            <td>
        <tr>
            <th>Genolve</th>
            <td>
                <input type="text" name="media_optimization_genolve_apikey" value="<?php echo esc_attr( get_option('media_optimization_genolve_apikey') ); ?>" />
            </td>
        </tr>
        </td>
        </tr>
    </table>
    <?php submit_button(); ?>
</form>