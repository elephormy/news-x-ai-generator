jQuery(document).ready(function($) {

    // NEW: Simplified frontend processing since AI now uses explicit markers
    // The server-side processing handles the [MINI_TITLE]...[/MINI_TITLE] markers
    // This frontend script now only handles any edge cases or dynamic content loading
    
    // Function to ensure proper spacing and styling for dynamically loaded content
    function enhanceContentDisplay() {
        // Ensure proper spacing around headings
        $('.newspaper-article h3').each(function() {
            var $heading = $(this);
            var $nextElement = $heading.next();
            
            // Add proper spacing after headings
            if ($nextElement.length && !$nextElement.hasClass('newspaper-article')) {
                $heading.css('margin-bottom', '15px');
            }
        });
        
        // Ensure proper spacing around paragraphs
        $('.newspaper-article p').each(function() {
            var $paragraph = $(this);
            var $prevElement = $paragraph.prev();
            
            // Add proper spacing before paragraphs (except after headings)
            if ($prevElement.length && !$prevElement.is('h3')) {
                $paragraph.css('margin-top', '15px');
            }
        });
    }
    
    // Run content enhancement when page loads
    enhanceContentDisplay();
    
    // Run content enhancement when new content is dynamically loaded
    $(document).on('DOMNodeInserted', '.newspaper-article', function() {
        setTimeout(enhanceContentDisplay, 100);
    });

}); 