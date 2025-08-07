jQuery(document).ready(function($) {
    
    console.log('News X AI Generator: Document ready');
    console.log('News X AI Generator: AJAX data available:', typeof news_x_ai_ajax !== 'undefined' ? 'Yes' : 'No');
    if (typeof news_x_ai_ajax !== 'undefined') {
        console.log('News X AI Generator: AJAX URL:', news_x_ai_ajax.ajax_url);
        console.log('News X AI Generator: Nonce:', news_x_ai_ajax.nonce);
    }
    
    // Test if buttons exist
    console.log('News X AI Generator: Test API button exists:', $('#test-api-btn').length > 0);
    console.log('News X AI Generator: Debug button exists:', $('#debug-btn').length > 0);
    console.log('News X AI Generator: Generate posts button exists:', $('#generate-posts-btn').length > 0);
    
    // Test API Connection
    $('#test-api-btn').on('click', function() {
        console.log('Test API button clicked');
        var $btn = $(this);
        var originalText = $btn.text();
        
        $btn.prop('disabled', true).text(news_x_ai_ajax.strings.testing);
        
        console.log('Making AJAX call to test API...');
        $.ajax({
            url: news_x_ai_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'news_x_ai_test_api',
                nonce: news_x_ai_ajax.nonce
            },
            success: function(response) {
                console.log('Test API response:', response);
                if (response.success) {
                    alert(news_x_ai_ajax.strings.api_success);
                } else {
                    alert(news_x_ai_ajax.strings.api_error + ': ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('Test API AJAX Error:', status, error);
                console.error('Response:', xhr.responseText);
                alert(news_x_ai_ajax.strings.api_error + ': ' + error);
            },
            complete: function() {
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // List Models button
    $(document).on('click', '#list-models-btn', function(e) {
        e.preventDefault();
        console.log('List Models button clicked');
        
        var button = $(this);
        var originalText = button.text();
        
        button.prop('disabled', true).text('Listing Models...');
        
        $.ajax({
            url: news_x_ai_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'news_x_ai_list_models',
                nonce: news_x_ai_ajax.nonce
            },
            success: function(response) {
                console.log('List models response:', response);
                if (response.success) {
                    var models = response.data.models;
                    var modelList = '';
                    
                    if (models && models.length > 0) {
                        modelList = '<h4>Available Models:</h4><ul>';
                        models.forEach(function(model) {
                            modelList += '<li><strong>' + model.name + '</strong>';
                            if (model.description) {
                                modelList += ' - ' + model.description;
                            }
                            if (model.supportedGenerationMethods) {
                                modelList += '<br><small>Supported methods: ' + model.supportedGenerationMethods.join(', ') + '</small>';
                            }
                            modelList += '</li>';
                        });
                        modelList += '</ul>';
                    } else {
                        modelList = '<p>No models found or error retrieving models.</p>';
                    }
                    
                    $('#debug-output').html(modelList);
                } else {
                    $('#debug-output').html('<p class="error">Error: ' + (response.data || 'Unknown error occurred') + '</p>');
                }
            },
            error: function(xhr, status, error) {
                console.log('List models AJAX error:', {xhr: xhr, status: status, error: error, responseText: xhr.responseText});
                $('#debug-output').html('<p class="error">AJAX Error: ' + error + '</p>');
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Debug button
    $('#debug-btn').on('click', function() {
        console.log('Debug button clicked');
        var $btn = $(this);
        var originalText = $btn.text();
        
        $btn.prop('disabled', true).text('Getting debug info...');
        
        console.log('Making AJAX call to debug...');
        $.ajax({
            url: news_x_ai_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'news_x_ai_debug',
                nonce: news_x_ai_ajax.nonce
            },
            success: function(response) {
                console.log('Debug response:', response);
                if (response.success) {
                    console.log('Debug Info:', response.data);
                    $('#debug-output').html('<pre>' + response.data + '</pre>');
                } else {
                    $('#debug-output').html('<p class="error">Debug Error: ' + (response.data || 'Unknown error occurred') + '</p>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Debug AJAX Error:', status, error);
                console.error('Response:', xhr.responseText);
                alert('Debug Error: ' + error);
            },
            complete: function() {
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Generate Posts
    $('#generate-posts-btn').on('click', function() {
        console.log('Generate posts button clicked');
        var $btn = $(this);
        var $progress = $('#generation-progress');
        var $progressText = $('#progress-text');
        var originalText = $btn.text();
        
        var count = parseInt($('#generate_count').val()) || 3;
        var categories = $('#generate_categories').val() || [];
        
        console.log('Generation parameters:', { count: count, categories: categories });
        
        $btn.prop('disabled', true).text(news_x_ai_ajax.strings.generating);
        $progress.show();
        $progressText.text(news_x_ai_ajax.strings.generating);
        
        // Animate progress bar
        var $progressFill = $('.progress-fill');
        $progressFill.css('width', '0%');
        
        var progressInterval = setInterval(function() {
            var currentWidth = parseInt($progressFill.css('width'));
            if (currentWidth < 90) {
                $progressFill.css('width', (currentWidth + 5) + '%');
            }
        }, 500);
        
        console.log('Making AJAX call to generate posts...');
        $.ajax({
            url: news_x_ai_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'news_x_ai_generate_posts',
                nonce: news_x_ai_ajax.nonce,
                count: count,
                categories: categories
            },
            success: function(response) {
                clearInterval(progressInterval);
                $progressFill.css('width', '100%');
                
                console.log('Generate posts response:', response);
                
                if (response.success) {
                    $progressText.text(news_x_ai_ajax.strings.success + ' (' + response.data.total_generated + ' posts)').css('color', '#46b450');
                    
                    // Show generated posts
                    if (response.data.posts && response.data.posts.length > 0) {
                        var postsHtml = '<div class="generated-posts">';
                        postsHtml += '<h3>Generated Posts:</h3>';
                        postsHtml += '<ul>';
                        
                        response.data.posts.forEach(function(post) {
                            postsHtml += '<li>';
                            postsHtml += '<strong>' + post.title + '</strong> - ';
                            postsHtml += '<span class="post-category">Category: ' + (post.category_name || 'General') + '</span> - ';
                            postsHtml += '<span class="post-status">Status: ' + (post.status || 'Published') + '</span> - ';
                            postsHtml += '<a href="' + post.edit_url + '" target="_blank">Edit</a> | ';
                            postsHtml += '<a href="' + post.url + '" target="_blank">View</a>';
                            postsHtml += '</li>';
                        });
                        
                        postsHtml += '</ul></div>';
                        
                        var $postsDiv = $(postsHtml);
                        $postsDiv.hide();
                        $progress.after($postsDiv);
                        $postsDiv.fadeIn(500);
                    }
                    
                    // Show errors if any
                    if (response.data.errors && response.data.errors.length > 0) {
                        var errorsHtml = '<div class="generation-errors">';
                        errorsHtml += '<h3>Errors:</h3>';
                        errorsHtml += '<ul>';
                        
                        response.data.errors.forEach(function(error) {
                            errorsHtml += '<li>' + error + '</li>';
                        });
                        
                        errorsHtml += '</ul></div>';
                        
                        var $errorsDiv = $(errorsHtml);
                        $errorsDiv.hide();
                        $progress.after($errorsDiv);
                        $errorsDiv.fadeIn(500);
                    }
                    
                    // Add a clear results button
                    var clearButton = '<button type="button" id="clear-results-btn" class="button button-secondary" style="margin-top: 10px;">Clear Results</button>';
                    $progress.after(clearButton);
                    
                    // Handle clear results button
                    $('#clear-results-btn').on('click', function() {
                        $('.generated-posts, .generation-errors, #clear-results-btn').remove();
                        $progress.hide();
                        $progressText.text('');
                    });
                    
                } else {
                    $progressText.text(news_x_ai_ajax.strings.error);
                    var errorMessage = response.data || 'Unknown error occurred';
                    console.error('Generation Error:', response);
                    alert(news_x_ai_ajax.strings.error + ': ' + errorMessage);
                }
            },
            error: function(xhr, status, error) {
                clearInterval(progressInterval);
                $progressFill.css('width', '100%');
                $progressText.text(news_x_ai_ajax.strings.error);
                console.error('AJAX Error:', status, error);
                console.error('Response:', xhr.responseText);
                alert(news_x_ai_ajax.strings.error + ': ' + (error || 'Network error'));
            },
            complete: function() {
                setTimeout(function() {
                    $btn.prop('disabled', false).text(originalText);
                }, 1000);
            }
        });
    });
    
    // Settings form validation
    $('form').on('submit', function(e) {
        var apiKey = $('#news_x_ai_gemini_api_key').val();
        
        if (!apiKey.trim()) {
            if (!confirm('You haven\'t entered a Gemini API key. The plugin won\'t work without it. Do you want to continue?')) {
                e.preventDefault();
                return false;
            }
        }
    });
    
    // Auto-generate toggle
    $('#news_x_ai_auto_generate').on('change', function() {
        var isChecked = $(this).is(':checked');
        var $frequencyField = $('#news_x_ai_generation_frequency').closest('tr');
        var $postsField = $('#news_x_ai_posts_per_generation').closest('tr');
        
        if (isChecked) {
            $frequencyField.show();
            $postsField.show();
        } else {
            $frequencyField.hide();
            $postsField.hide();
        }
    }).trigger('change');
    
    // Category selection enhancement
    $('#news_x_ai_categories input[type="checkbox"]').on('change', function() {
        var checkedCount = $('#news_x_ai_categories input[type="checkbox"]:checked').length;
        
        if (checkedCount === 0) {
            $(this).prop('checked', true);
            alert('Please select at least one category.');
        }
    });
    
    // Image source change handler
    $('#news_x_ai_image_source').on('change', function() {
        var selectedSource = $(this).val();
        var $qualityField = $('#news_x_ai_image_quality').closest('tr');
        
        // Show/hide quality options based on source
        if (selectedSource === 'unsplash' || selectedSource === 'pexels') {
            $qualityField.show();
        } else {
            $qualityField.hide();
        }
    }).trigger('change');
    
    // Content length change handler
    $('#news_x_ai_content_length').on('change', function() {
        var selectedLength = $(this).val();
        var wordCount = '';
        
        switch (selectedLength) {
            case 'short':
                wordCount = '300-500 words';
                break;
            case 'medium':
                wordCount = '500-800 words';
                break;
            case 'long':
                wordCount = '800-1200 words';
                break;
        }
        
        // Update description if needed
        var $description = $(this).closest('td').find('.description');
        if ($description.length === 0) {
            $(this).closest('td').append('<p class="description">Target: ' + wordCount + '</p>');
        } else {
            $description.text('Target: ' + wordCount);
        }
    }).trigger('change');
    
    // Settings sections toggle
    $('.news-x-ai-section h2').on('click', function() {
        var $section = $(this).closest('.news-x-ai-section');
        var $content = $section.find('table');
        
        if ($content.is(':visible')) {
            $content.slideUp();
            $(this).addClass('collapsed');
        } else {
            $content.slideDown();
            $(this).removeClass('collapsed');
        }
    });
    
    // Add collapse/expand functionality
    $('.news-x-ai-section h2').each(function() {
        $(this).append('<span class="toggle-icon">▼</span>');
    });
    
    // Initialize tooltips
    $('[data-tooltip]').on('mouseenter', function() {
        var tooltip = $(this).data('tooltip');
        var $tooltip = $('<div class="tooltip">' + tooltip + '</div>');
        
        $('body').append($tooltip);
        
        var offset = $(this).offset();
        $tooltip.css({
            top: offset.top - $tooltip.outerHeight() - 10,
            left: offset.left + ($(this).outerWidth() / 2) - ($tooltip.outerWidth() / 2)
        });
        
        $tooltip.fadeIn();
    }).on('mouseleave', function() {
        $('.tooltip').remove();
    });
    
    // Form field validation
    $('input[type="number"]').on('input', function() {
        var min = $(this).attr('min');
        var max = $(this).attr('max');
        var value = parseInt($(this).val());
        
        if (min && value < parseInt(min)) {
            $(this).val(min);
        }
        
        if (max && value > parseInt(max)) {
            $(this).val(max);
        }
    });
    
    // Save settings confirmation
    $('form').on('submit', function() {
        var autoGenerate = $('#news_x_ai_auto_generate').is(':checked');
        var frequency = $('#news_x_ai_generation_frequency').val();
        
        if (autoGenerate) {
            var message = 'You have enabled automatic post generation with ' + frequency + ' frequency. ';
            message += 'This will generate posts automatically based on your schedule. ';
            message += 'Are you sure you want to save these settings?';
            
            if (!confirm(message)) {
                return false;
            }
        }
    });
    
    // Real-time character count for text areas
    $('textarea').on('input', function() {
        var maxLength = $(this).attr('maxlength');
        if (maxLength) {
            var currentLength = $(this).val().length;
            var remaining = maxLength - currentLength;
            
            var $counter = $(this).siblings('.char-counter');
            if ($counter.length === 0) {
                $counter = $('<span class="char-counter"></span>');
                $(this).after($counter);
            }
            
            $counter.text(remaining + ' characters remaining');
            
            if (remaining < 0) {
                $counter.addClass('error');
            } else {
                $counter.removeClass('error');
            }
        }
    });
    
    // Settings import/export (placeholder for future functionality)
    $('#export-settings').on('click', function(e) {
        e.preventDefault();
        alert('Settings export functionality will be available in a future version.');
    });
    
    $('#import-settings').on('click', function(e) {
        e.preventDefault();
        alert('Settings import functionality will be available in a future version.');
    });
    
    // Help modal
    $('.help-link').on('click', function(e) {
        e.preventDefault();
        var helpContent = $(this).data('help');
        
        var $modal = $('<div class="help-modal">' +
            '<div class="help-content">' +
            '<span class="close">&times;</span>' +
            '<h3>Help</h3>' +
            '<div class="help-text">' + helpContent + '</div>' +
            '</div>' +
            '</div>');
        
        $('body').append($modal);
        $modal.fadeIn();
        
        $modal.find('.close, .help-modal').on('click', function() {
            $modal.fadeOut(function() {
                $modal.remove();
            });
        });
    });
    
    // Initialize any additional UI enhancements
    initUIEnhancements();
    
    // NEW: Enhanced heading detection for frontend
    enhanceHeadingDetection();
    
    function initUIEnhancements() {
        // Add loading states to buttons
        $('.button').on('click', function() {
            var $btn = $(this);
            if (!$btn.hasClass('no-loading')) {
                $btn.addClass('loading');
                setTimeout(function() {
                    $btn.removeClass('loading');
                }, 2000);
            }
        });
        
        // Add confirmation for destructive actions
        $('.delete-action').on('click', function(e) {
            if (!confirm('Are you sure you want to perform this action? This cannot be undone.')) {
                e.preventDefault();
                return false;
            }
        });
    }
    
    // NEW: Function to enhance heading detection on the frontend
    function enhanceHeadingDetection() {
        // Look for any paragraphs that look like headings but weren't detected
        $('.newspaper-article p').each(function() {
            var $paragraph = $(this);
            var text = $paragraph.text().trim();
            
            // Skip if already has heading styling
            if ($paragraph.hasClass('lead-paragraph') || $paragraph.hasClass('heading-style')) {
                return;
            }
            
            // Check if this paragraph looks like a heading
            if (isHeadingText(text)) {
                $paragraph.addClass('heading-style');
            }
        });
    }
    
    // NEW: Function to check if text looks like a heading
    function isHeadingText(text) {
        // Must start with capital letter
        if (!/^[A-Z]/.test(text)) {
            return false;
        }
        
        // Must not end with sentence punctuation
        if (/[.!?]$/.test(text)) {
            return false;
        }
        
        // Must be reasonably short
        if (text.length > 150) {
            return false;
        }
        
        // Must not contain multiple sentences
        if ((text.match(/\./g) || []).length > 0) {
            return false;
        }
        
        // Check for common heading patterns
        var headingPatterns = [
            // Ends with colon
            /^[A-Z][^.!?]*:$/,
            
            // Contains heading keywords
            /\b(Impact|Outlook|Analysis|Overview|Summary|Conclusion|Introduction|Background|Context|Development|Progress|Challenge|Solution|Strategy|Approach|Method|Technique|System|Process|Framework|Model|Theory|Concept|Principle|Factor|Element|Component|Aspect|Dimension|Perspective|Viewpoint|Position|Assessment|Evaluation|Review|Examination|Investigation|Study|Research|Discussion|Debate|Argument|Issue|Problem|Question|Subject|Topic|Theme|Focus|Emphasis|Priority|Importance|Relevance|Significance|Value|Benefit|Advantage|Disadvantage|Risk|Threat|Opportunity|Obstacle|Barrier|Limitation|Constraint|Requirement|Condition|Criterion|Standard|Guideline|Policy|Rule|Regulation|Law|Act|Bill|Proposal|Plan|Program|Initiative|Campaign|Project|Operation|Activity|Event|Occasion|Situation|Circumstance|Environment|Setting|History|Tradition|Culture|Society|Community|Group|Organization|Institution|Company|Business|Industry|Sector|Field|Domain|Area|Region|Zone|Territory|Country|Nation|State|Province|City|Town|Village|District|Neighborhood|Location|Place|Site|Spot|Point|Position|Significance|Tournament|Teams|Future|Statistical|Resource|Optimization|Sustainability|UrbanOS|Predictive|Analytics|Urban|Systems|Transportation|Waste|Management|Energy|Grids|Infrastructure|Streetlights|Traffic|Signals|Consumption|Congestion|Catalyst|Initiatives|Adoption|Improvement|Ethics|Transparency|Governments|Municipalities|Privacy|Bias|Blueprint|Partnerships|Challenges|Pilot|Programs|Metropolitan|Efficiency|Services|Consortium|Support|Training|Implementation)\b/i,
            
            // Short capitalized phrases
            /^[A-Z][a-z]+(?:\s+[A-Z][a-z]+){0,3}$/,
            
            // Starts with "The" and contains heading keywords
            /^The\s+\b(Impact|Outlook|Analysis|Overview|Summary|Conclusion|Introduction|Background|Context|Development|Progress|Challenge|Solution|Strategy|Approach|Method|Technique|System|Process|Framework|Model|Theory|Concept|Principle|Factor|Element|Component|Aspect|Dimension|Perspective|Viewpoint|Position|Assessment|Evaluation|Review|Examination|Investigation|Study|Research|Discussion|Debate|Argument|Issue|Problem|Question|Subject|Topic|Theme|Focus|Emphasis|Priority|Importance|Relevance|Significance|Value|Benefit|Advantage|Disadvantage|Risk|Threat|Opportunity|Obstacle|Barrier|Limitation|Constraint|Requirement|Condition|Criterion|Standard|Guideline|Policy|Rule|Regulation|Law|Act|Bill|Proposal|Plan|Program|Initiative|Campaign|Project|Operation|Activity|Event|Occasion|Situation|Circumstance|Environment|Setting|History|Tradition|Culture|Society|Community|Group|Organization|Institution|Company|Business|Industry|Sector|Field|Domain|Area|Region|Zone|Territory|Country|Nation|State|Province|City|Town|Village|District|Neighborhood|Location|Place|Site|Spot|Point|Position|Significance|Tournament|Teams|Future|Statistical|Resource|Optimization|Sustainability|UrbanOS|Predictive|Analytics|Urban|Systems|Transportation|Waste|Management|Energy|Grids|Infrastructure|Streetlights|Traffic|Signals|Consumption|Congestion|Catalyst|Initiatives|Adoption|Improvement|Ethics|Transparency|Governments|Municipalities|Privacy|Bias|Blueprint|Partnerships|Challenges|Pilot|Programs|Metropolitan|Efficiency|Services|Consortium|Support|Training|Implementation)\b/i,
            
            // Starts with "A" or "An" and contains heading keywords
            /^(A|An)\s+\b(Impact|Outlook|Analysis|Overview|Summary|Conclusion|Introduction|Background|Context|Development|Progress|Challenge|Solution|Strategy|Approach|Method|Technique|System|Process|Framework|Model|Theory|Concept|Principle|Factor|Element|Component|Aspect|Dimension|Perspective|Viewpoint|Position|Assessment|Evaluation|Review|Examination|Investigation|Study|Research|Discussion|Debate|Argument|Issue|Problem|Question|Subject|Topic|Theme|Focus|Emphasis|Priority|Importance|Relevance|Significance|Value|Benefit|Advantage|Disadvantage|Risk|Threat|Opportunity|Obstacle|Barrier|Limitation|Constraint|Requirement|Condition|Criterion|Standard|Guideline|Policy|Rule|Regulation|Law|Act|Bill|Proposal|Plan|Program|Initiative|Campaign|Project|Operation|Activity|Event|Occasion|Situation|Circumstance|Environment|Setting|History|Tradition|Culture|Society|Community|Group|Organization|Institution|Company|Business|Industry|Sector|Field|Domain|Area|Region|Zone|Territory|Country|Nation|State|Province|City|Town|Village|District|Neighborhood|Location|Place|Site|Spot|Point|Position|Significance|Tournament|Teams|Future|Statistical|Resource|Optimization|Sustainability|UrbanOS|Predictive|Analytics|Urban|Systems|Transportation|Waste|Management|Energy|Grids|Infrastructure|Streetlights|Traffic|Signals|Consumption|Congestion|Catalyst|Initiatives|Adoption|Improvement|Ethics|Transparency|Governments|Municipalities|Privacy|Bias|Blueprint|Partnerships|Challenges|Pilot|Programs|Metropolitan|Efficiency|Services|Consortium|Support|Training|Implementation)\b/i,
            
            // ENHANCED: Catch more specific heading patterns
            /^[A-Z][a-z]+(?:\s+[A-Z][a-z]+)*\s+(Impact|Outlook|Analysis|Overview|Summary|Conclusion|Introduction|Background|Context|Development|Progress|Challenge|Solution|Strategy|Approach|Method|Technique|System|Process|Framework|Model|Theory|Concept|Principle|Factor|Element|Component|Aspect|Dimension|Perspective|Viewpoint|Position|Assessment|Evaluation|Review|Examination|Investigation|Study|Research|Discussion|Debate|Argument|Issue|Problem|Question|Subject|Topic|Theme|Focus|Emphasis|Priority|Importance|Relevance|Significance|Value|Benefit|Advantage|Disadvantage|Risk|Threat|Opportunity|Obstacle|Barrier|Limitation|Constraint|Requirement|Condition|Criterion|Standard|Guideline|Policy|Rule|Regulation|Law|Act|Bill|Proposal|Plan|Program|Initiative|Campaign|Project|Operation|Activity|Event|Occasion|Situation|Circumstance|Environment|Setting|History|Tradition|Culture|Society|Community|Group|Organization|Institution|Company|Business|Industry|Sector|Field|Domain|Area|Region|Zone|Territory|Country|Nation|State|Province|City|Town|Village|District|Neighborhood|Location|Place|Site|Spot|Point|Position|Significance|Tournament|Teams|Future|Statistical|Resource|Optimization|Sustainability|UrbanOS|Predictive|Analytics|Urban|Systems|Transportation|Waste|Management|Energy|Grids|Infrastructure|Streetlights|Traffic|Signals|Consumption|Congestion|Catalyst|Initiatives|Adoption|Improvement|Ethics|Transparency|Governments|Municipalities|Privacy|Bias|Blueprint|Partnerships|Challenges|Pilot|Programs|Metropolitan|Efficiency|Services|Consortium|Support|Training|Implementation)$/i,
            
            // ENHANCED: Catch phrases that end with common heading words
            /^[A-Z][^.!?]*\s+(Impact|Outlook|Analysis|Overview|Summary|Conclusion|Introduction|Background|Context|Development|Progress|Challenge|Solution|Strategy|Approach|Method|Technique|System|Process|Framework|Model|Theory|Concept|Principle|Factor|Element|Component|Aspect|Dimension|Perspective|Viewpoint|Position|Assessment|Evaluation|Review|Examination|Investigation|Study|Research|Discussion|Debate|Argument|Issue|Problem|Question|Subject|Topic|Theme|Focus|Emphasis|Priority|Importance|Relevance|Significance|Value|Benefit|Advantage|Disadvantage|Risk|Threat|Opportunity|Obstacle|Barrier|Limitation|Constraint|Requirement|Condition|Criterion|Standard|Guideline|Policy|Rule|Regulation|Law|Act|Bill|Proposal|Plan|Program|Initiative|Campaign|Project|Operation|Activity|Event|Occasion|Situation|Circumstance|Environment|Setting|History|Tradition|Culture|Society|Community|Group|Organization|Institution|Company|Business|Industry|Sector|Field|Domain|Area|Region|Zone|Territory|Country|Nation|State|Province|City|Town|Village|District|Neighborhood|Location|Place|Site|Spot|Point|Position|Significance|Tournament|Teams|Future|Statistical|Resource|Optimization|Sustainability|UrbanOS|Predictive|Analytics|Urban|Systems|Transportation|Waste|Management|Energy|Grids|Infrastructure|Streetlights|Traffic|Signals|Consumption|Congestion|Catalyst|Initiatives|Adoption|Improvement|Ethics|Transparency|Governments|Municipalities|Privacy|Bias|Blueprint|Partnerships|Challenges|Pilot|Programs|Metropolitan|Efficiency|Services|Consortium|Support|Training|Implementation)$/i
        ];
        
        for (var i = 0; i < headingPatterns.length; i++) {
            if (headingPatterns[i].test(text)) {
                return true;
            }
        }
        
        // Additional check for short phrases
        var wordCount = text.split(/\s+/).length;
        if (wordCount >= 2 && wordCount <= 6) {
            var sentenceIndicators = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'has', 'have', 'had', 'will', 'would', 'could', 'should', 'may', 'might', 'can', 'must', 'shall'];
            var words = text.toLowerCase().split(/\s+/);
            var sentenceWordCount = 0;
            
            for (var j = 0; j < words.length; j++) {
                if (sentenceIndicators.indexOf(words[j]) !== -1) {
                    sentenceWordCount++;
                }
            }
            
            if (sentenceWordCount < words.length * 0.5) {
                return true;
            }
        }
        
        return false;
    }
}); 