jQuery(document).ready(function($) {
    var currentQuestion = 0;
    var totalQuestions = $('.quiz-question').length;
    var answers = {};
    
    // Modal functions
    function showModal(message, type) {
        type = type || 'info'; // info, error, success
        var $modal = $('.quiz-modal');
        var $modalContent = $('.quiz-modal-content');
        var $modalMessage = $('.quiz-modal-message');
        var $modalTitle = $('.quiz-modal-title');
        
        // Set title based on type
        var title = 'Information';
        if (type === 'error') {
            title = 'Error';
        } else if (type === 'success') {
            title = 'Success';
        }
        
        $modalTitle.text(title);
        $modalMessage.text(message);
        $modalContent.removeClass('quiz-modal-error quiz-modal-success quiz-modal-info');
        $modalContent.addClass('quiz-modal-' + type);
        $modal.fadeIn(300);
    }
    
    function hideModal() {
        $('.quiz-modal').fadeOut(300);
    }
    
    // Close modal on button click or overlay click
    $(document).on('click', '.quiz-modal-overlay', function(e) {
        if ($(e.target).hasClass('quiz-modal-overlay')) {
            hideModal();
        }
    });
    
    $(document).on('click', '.quiz-modal-close', function(e) {
        e.preventDefault();
        e.stopPropagation();
        hideModal();
    });
    
    // Close modal on Escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('.quiz-modal').is(':visible')) {
            hideModal();
        }
    });
    
    // Update progress bar
    function updateProgress() {
        var progress = ((currentQuestion + 1) / totalQuestions) * 100;
        var progressPercent = Math.round(progress);
        $('.progress-fill').css('width', progress + '%');
        $('.current-question').text(currentQuestion + 1);
        $('.progress-percentage').text(progressPercent + '%');
    }

    // Show a specific question index
    function showQuestion(index) {
        if (index < 0 || index >= totalQuestions) return;

        $('.quiz-question').hide();
        $('.quiz-email-section').hide();

        currentQuestion = index;
        var $q = $('.quiz-question').eq(currentQuestion);

        $q.fadeIn(300, function () {
            // Back button visibility
            if (currentQuestion === 0) {
                $q.find('.quiz-back-btn').hide();
            } else {
                $q.find('.quiz-back-btn').show();
            }

            // If this question already has an answer, show Next button
            var hasAnswer =
                $q.find('input[type="radio"]:checked').length > 0 ||
                $q.find('input[type="checkbox"]:checked').length > 0 ||
                ($q.find('select').length && $q.find('select').val());

            if (hasAnswer) {
                $q.find('.quiz-next-btn').show();
            }
        });

        updateProgress();
    }

    // Collect and validate current question's answer, then move forward
    function goNext() {
        var $currentQ = $('.quiz-question').eq(currentQuestion);
        var questionId = $currentQ.data('question-id');
        var answerType = $currentQ.find('input[type="radio"], input[type="checkbox"], select').first();

        // Get selected answer
        var selectedAnswer = null;

        if (answerType.is('input[type="radio"]')) {
            selectedAnswer = $currentQ.find('input[type="radio"]:checked').val();
        } else if (answerType.is('input[type="checkbox"]')) {
            var checked = $currentQ.find('input[type="checkbox"]:checked');
            if (checked.length > 0) {
                selectedAnswer = checked.map(function () {
                    return $(this).val();
                }).get();
            }
        } else if (answerType.is('select')) {
            selectedAnswer = $currentQ.find('select').val();
        }

        // Validate answer
        if (!selectedAnswer || (Array.isArray(selectedAnswer) && selectedAnswer.length === 0)) {
            showModal('Please select an answer before proceeding.', 'info');
            return;
        }

        // Store answer
        answers[questionId] = selectedAnswer;

        // Move forward
        $currentQ.fadeOut(300, function () {
            if (currentQuestion + 1 < totalQuestions) {
                showQuestion(currentQuestion + 1);
            } else {
                // Last question -> show email section
                $('.quiz-email-section').fadeIn(300);
                $('.progress-fill').css('width', '100%');
                $('.progress-percentage').text('100%');
            }
        });
    }

    // Move back without changing stored answers
    function goBack() {
        if (currentQuestion === 0) return;

        var $currentQ = $('.quiz-question').eq(currentQuestion);
        $currentQ.fadeOut(300, function () {
            showQuestion(currentQuestion - 1);
        });
    }

    // Handle next button click
    $(document).on('click', '.quiz-next-btn', function () {
        goNext();
    });

    // Handle back button click
    $(document).on('click', '.quiz-back-btn', function () {
        goBack();
    });
    
    // Handle form submission
    $('#quiz-form').on('submit', function(e) {
        e.preventDefault();
        
        var email = $('#quiz_email').val();
        
        if (!email || !isValidEmail(email)) {
            showModal('Please enter a valid email address.', 'error');
            return;
        }
        
        // Disable submit button
        $('.quiz-submit-btn').prop('disabled', true).text('Submitting...');
        
        // Submit via AJAX
        $.ajax({
            url: quizAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'submit_quiz',
                nonce: quizAjax.nonce,
                email: email,
                answers: answers
            },
            success: function(response) {
                if (response.success) {
                    // Hide form and show results
                    $('#quiz-form').fadeOut(300, function() {
                        var resultsHtml = '<h3>Quiz Submitted Successfully!</h3>';
                        resultsHtml += '<div class="score">';
                        resultsHtml += 'Score: ' + response.data.correct + ' / ' + response.data.total;
                        resultsHtml += ' (' + response.data.score + '%)';
                        resultsHtml += '</div>';
                        resultsHtml += '<p>' + response.data.message + '</p>';
                        
                        $('.quiz-results').html(resultsHtml).fadeIn(300);
                    });
                } else {
                    showModal(response.data.message || 'An error occurred. Please try again.', 'error');
                    $('.quiz-submit-btn').prop('disabled', false).text('Submit Quiz');
                }
            },
            error: function() {
                showModal('An error occurred. Please try again.', 'error');
                $('.quiz-submit-btn').prop('disabled', false).text('Submit Quiz');
            }
        });
    });
    
    // Email validation
    function isValidEmail(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    // Initialize first question / progress
    showQuestion(0);
    
    // Allow Enter key to proceed (for dropdowns)
    $(document).on('change', 'select', function() {
        var $question = $(this).closest('.quiz-question');
        if ($question.find('select').val()) {
            setTimeout(function() {
                $question.find('.quiz-next-btn').show();
            }, 100);
        }
    });
    
    // Show next button when answer is selected (for radio/checkbox)
    $(document).on('change', 'input[type="radio"], input[type="checkbox"]', function() {
        var $question = $(this).closest('.quiz-question');
        $question.find('.quiz-next-btn').show();
    });
});

