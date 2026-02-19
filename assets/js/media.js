jQuery(document).ready(function($) {
    
    // Handle enhance/video buttons in media library
    $('.pixelpapa-enhance, .pixelpapa-video').on('click', function(e) {
        if (!confirm(pixelpapa.strings.confirm)) {
            e.preventDefault();
            return false;
        }
        
        var $link = $(this);
        var originalText = $link.text();
        
        $link.text(pixelpapa.strings.processing).addClass('disabled');
        
        // Show loading spinner
        $link.closest('.row-actions').append('<span class="spinner is-active" style="float: none; margin: 0;"></span>');
    });
    
    // Handle bulk actions confirmation
    $('#doaction, #doaction2').on('click', function(e) {
        var action = $(this).closest('.bulkactions').find('select[name="action"]').val();
        
        if (action && action.indexOf('pixelpapa_') === 0) {
            if (!confirm(pixelpapa.strings.confirm)) {
                e.preventDefault();
                return false;
            }
        }
    });
    
});
