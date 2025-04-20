document.addEventListener('DOMContentLoaded', function() {
    const addOptionBtn = document.getElementById('add-option');
    const optionsContainer = document.getElementById('options-container');
    const pollForm = document.getElementById('poll-form');
    let optionCount = initialOptionCount || 2;
    
    // Add option
    addOptionBtn.addEventListener('click', function() {
        if (optionCount >= 5) {
            alert('You can add up to 5 options only.');
            return;
        }
        
        optionCount++;
        const newOption = document.createElement('div');
        newOption.className = 'option-container';
        newOption.innerHTML = `
            <input type="text" name="option${optionCount}" class="form-control" placeholder="Option ${optionCount}">
            <button type="button" class="btn-remove remove-option">Remove</button>
        `;
        optionsContainer.appendChild(newOption);
        
        // Add remove event
        newOption.querySelector('.remove-option').addEventListener('click', function() {
            removeOption(this.parentNode);
        });
    });
    
    // Add remove event to existing buttons
    document.querySelectorAll('.remove-option').forEach(btn => {
        btn.addEventListener('click', function() {
            removeOption(this.parentNode);
        });
    });
    
    // Remove option function
    function removeOption(optionElement) {
        if (document.querySelectorAll('.option-container').length <= 2) {
            alert('At least 2 options must remain.');
            return;
        }
        optionsContainer.removeChild(optionElement);
        optionCount--;
    }
    
    // Form submission validation
    pollForm.addEventListener('submit', function(e) {
        const question = document.getElementById('question').value.trim();
        const options = document.querySelectorAll('[name^="option"]');
        let filledOptions = 0;
        
        if (!question) {
            e.preventDefault();
            alert('Please enter the poll question.');
            return;
        }
        
        options.forEach(option => {
            if (option.value.trim().length > 0) {
                filledOptions++;
            }
        });
        
        if (filledOptions < 2) {
            e.preventDefault();
            alert('At least 2 non-empty options are required.');
            return;
        }
    });
});