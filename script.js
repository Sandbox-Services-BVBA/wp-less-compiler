jQuery(document).ready(function($){
    
    jQuery('#rdev_less_addsource_absolute_toggle').change(function(e) {
        jQuery('.rdev_less_addsource .relative-prefix').toggle();
    });

    jQuery('.rdev-less-source .remove-source').on('click', function(e) {
        var form = jQuery(this).parents('form:first');
        jQuery(form).find('input[name="rdev_fileid"]').val(jQuery(this).data('fileid'));
        jQuery(form).find('input[name="rdev_action"]').val('removesource');
        jQuery(form).submit();
    });

    jQuery('.rdev-less-source .toggle-source').on('click', function(e) {
        var form = jQuery(this).parents('form:first');
        jQuery(form).find('input[name="rdev_fileid"]').val(jQuery(this).data('fileid'));
        jQuery(form).find('input[name="rdev_action"]').val('togglesource');
        jQuery(form).submit();
    });

    jQuery('.rdev-less-var .remove-var').on('click', function(e) {
        var form = jQuery(this).parents('form:first');
        jQuery(form).find('input[name="rdev_varname"]').val(jQuery(this).data('varname'));
        jQuery(form).find('input[name="rdev_action"]').val('removevar');
        jQuery(form).submit();
    });

    jQuery('.rdev-less-var .edit-var').on('click', function(e) {
        var form = jQuery('form#variable-editor');
        jQuery(form).find('input[name="rdev_varname"]').val(jQuery(this).data('varname'));
        jQuery(form).find('input[name="rdev_varvalue"]').val(jQuery(this).data('varvalue'));
    });

});