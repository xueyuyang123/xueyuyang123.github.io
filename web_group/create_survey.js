document.addEventListener('DOMContentLoaded', function() {
    const addQuestionBtn = document.getElementById('add-question');
    const questionsContainer = document.getElementById('questions-container');
    const surveyForm = document.getElementById('survey-form');
    let questionCount = initialQuestionCount || 1;
    
    // Check if form should be cleared (after successful submission)
    const formCleared = document.querySelector('input[name="form_cleared"]');
    if (formCleared && formCleared.value === '1') {
        resetForm();
    }
    
    // Function to reset the form to initial state
    function resetForm() {
        surveyForm.reset();
        questionsContainer.innerHTML = `
            <div class="question-container" data-question-index="0">
                <div class="question-header">
                    <h3>Question #1</h3>
                    <button type="button" class="btn-remove remove-question">Remove Question</button>
                </div>
                
                <div class="form-group">
                    <label for="question_0" class="required">Question Text</label>
                    <input type="text" id="question_0" name="questions[0][text]" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="questions[0][is_multiple]">
                        Allow Multiple Selections
                    </label>
                </div>
                
                <div class="form-group">
                    <label class="required">Options (At least 2, up to 5, plus 'Other')</label>
                    <div class="options-container">
                        <div class="option-container">
                            <input type="text" name="questions[0][options][]" class="form-control" required>
                        </div>
                        <div class="option-container">
                            <input type="text" name="questions[0][options][]" class="form-control" required>
                        </div>
                        
                        <!-- Fixed "Other" option -->
                        <div class="option-container other-option">
                            <input type="text" class="form-control" value="Other" disabled>
                            <small class="form-text text-muted">This option allows users to input their own response.</small>
                        </div>
                    </div>
                    <button type="button" class="btn btn-add add-option">Add Option</button>
                </div>
            </div>
        `;
        questionCount = 1;
        
        // Re-attach event listeners for the new question
        const newQuestion = questionsContainer.querySelector('.question-container');
        newQuestion.querySelector('.remove-question').addEventListener('click', function() {
            removeQuestion(this.closest('.question-container'));
        });
        newQuestion.querySelector('.add-option').addEventListener('click', function() {
            addOptionToQuestion(this.closest('.question-container'));
        });
    }
    
    // Add question
    addQuestionBtn.addEventListener('click', function() {
        if (questionCount >= 10) {
            alert('You can add up to 10 questions per survey.');
            return;
        }
        
        const newQuestionIndex = questionCount;
        const newQuestion = document.createElement('div');
        newQuestion.className = 'question-container new-option';
        newQuestion.setAttribute('data-question-index', newQuestionIndex);
        newQuestion.innerHTML = `
            <div class="question-header">
                <h3>Question #${newQuestionIndex + 1}</h3>
                <button type="button" class="btn-remove remove-question">Remove Question</button>
            </div>
            
            <div class="form-group">
                <label for="question_${newQuestionIndex}" class="required">Question Text</label>
                <input type="text" id="question_${newQuestionIndex}" name="questions[${newQuestionIndex}][text]" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="questions[${newQuestionIndex}][is_multiple]">
                    Allow Multiple Selections
                </label>
            </div>
            
            <div class="form-group">
                <label class="required">Options (At least 2, up to 5, plus 'Other')</label>
                <div class="options-container">
                    <div class="option-container">
                        <input type="text" name="questions[${newQuestionIndex}][options][]" class="form-control" required>
                    </div>
                    <div class="option-container">
                        <input type="text" name="questions[${newQuestionIndex}][options][]" class="form-control" required>
                    </div>
                    
                    <!-- Fixed "Other" option -->
                    <div class="option-container other-option">
                        <input type="text" class="form-control" value="Other" disabled>
                        <small class="form-text text-muted">This option allows users to input their own response.</small>
                    </div>
                </div>
                <button type="button" class="btn btn-add add-option">Add Option</button>
            </div>
        `;
        
        questionsContainer.appendChild(newQuestion);
        questionCount++;
        
        // Add event listeners for the new question
        newQuestion.querySelector('.remove-question').addEventListener('click', function() {
            removeQuestion(this.closest('.question-container'));
        });
        
        // Add option button for this question
        newQuestion.querySelector('.add-option').addEventListener('click', function() {
            addOptionToQuestion(this.closest('.question-container'));
        });
        
        // Add remove option buttons
        newQuestion.querySelectorAll('.remove-option').forEach(btn => {
            btn.addEventListener('click', function() {
                removeOption(this.parentNode);
            });
        });
    });
    
    // Add option to a specific question
    function addOptionToQuestion(questionContainer) {
        const optionsContainer = questionContainer.querySelector('.options-container');
        const optionInputs = questionContainer.querySelectorAll('[name^="options"]');
        
        // Count only user-defined options (exclude "Other")
        const userDefinedOptions = questionContainer.querySelectorAll('.option-container:not(.other-option)').length;
        if (userDefinedOptions >= 5) {
            alert('You can add up to 5 options per question (excluding "Other").');
            return;
        }
        
        const questionIndex = questionContainer.getAttribute('data-question-index');
        const newOption = document.createElement('div');
        newOption.className = 'option-container';
        newOption.innerHTML = `
            <input type="text" name="questions[${questionIndex}][options][]" class="form-control">
            <button type="button" class="btn-remove remove-option">Remove</button>
        `;
        
        // Insert before the "Other" option
        const otherOption = questionContainer.querySelector('.other-option');
        optionsContainer.insertBefore(newOption, otherOption);
        
        // Add remove event
        newOption.querySelector('.remove-option').addEventListener('click', function() {
            removeOption(this.parentNode);
        });
    }
    
    // Remove question
    function removeQuestion(questionElement) {
        if (document.querySelectorAll('.question-container').length <= 1) {
            alert('At least one question must remain.');
            return;
        }
        
        questionElement.classList.add('removing');
        setTimeout(() => {
            questionsContainer.removeChild(questionElement);
            questionCount--;
            
            // Reindex remaining questions
            document.querySelectorAll('.question-container').forEach((container, index) => {
                container.setAttribute('data-question-index', index);
                container.querySelector('h3').textContent = `Question #${index + 1}`;
                
                // Update all input names
                const questionText = container.querySelector('[name^="questions"]');
                const newName = questionText.name.replace(/questions\[\d+\]/, `questions[${index}]`);
                questionText.name = newName;
                
                // Update option names
                container.querySelectorAll('[name^="questions"]').forEach(input => {
                    const newName = input.name.replace(/questions\[\d+\]/, `questions[${index}]`);
                    input.name = newName;
                });
            });
        }, 300);
    }
    
    // Remove option
    function removeOption(optionElement) {
        const questionContainer = optionElement.closest('.question-container');
        const userDefinedOptions = questionContainer.querySelectorAll('.option-container:not(.other-option)');
        
        if (userDefinedOptions.length <= 2) {
            alert('At least 2 options must remain (excluding "Other").');
            return;
        }
        
        optionElement.classList.add('removing');
        setTimeout(() => {
            optionElement.parentNode.removeChild(optionElement);
        }, 300);
    }
    
    // Add event listeners to existing elements
    document.querySelectorAll('.remove-question').forEach(btn => {
        btn.addEventListener('click', function() {
            removeQuestion(this.closest('.question-container'));
        });
    });
    
    document.querySelectorAll('.add-option').forEach(btn => {
        btn.addEventListener('click', function() {
            addOptionToQuestion(this.closest('.question-container'));
        });
    });
    
    document.querySelectorAll('.remove-option').forEach(btn => {
        btn.addEventListener('click', function() {
            removeOption(this.parentNode);
        });
    });
    
    // Form submission validation
    surveyForm.addEventListener('submit', function(e) {
        const surveyTitle = document.getElementById('survey_title').value.trim();
        const questions = document.querySelectorAll('.question-container');
        
        if (!surveyTitle) {
            e.preventDefault();
            alert('Please enter the survey title.');
            return;
        }
        
        if (questions.length === 0) {
            e.preventDefault();
            alert('At least one question is required.');
            return;
        }
        
        let isValid = true;
        questions.forEach((question, qIndex) => {
            const questionText = question.querySelector('[name^="questions"][name$="[text]"]').value.trim();
            // Select the user's chosen option, excluding "Other"
            const options = question.querySelectorAll('.option-container:not(.other-option) input[name^="questions"][name$="[options][]"]');
            let filledOptions = 0;
            
            if (!questionText) {
                isValid = false;
                alert(`Please enter text for Question #${qIndex + 1}`);
                return;
            }
            
            options.forEach(option => {
                if (option.value.trim().length > 0) {
                    filledOptions++;
                }
            });
            
            if (filledOptions < 2) {
                isValid = false;
                alert(`Question #${qIndex + 1} needs at least 2 non-empty options (excluding "Other").`);
                return;
            }
        });
        
        if (!isValid) {
            e.preventDefault();
        }
    });
});