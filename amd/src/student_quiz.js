define(['jquery'], function($) {

    return {
        init: function(courseid) {

            console.log("student_quiz.js loaded");
            console.log("Course ID received:", courseid);

            if (!courseid) {
                console.error("Course ID not received in JS");
                return;
            }


            $(function() {

                const apiBase = "http://127.0.0.1:8000";
                const quizContainer = $('#quizContainer');
                const unitContainer = $('#unitContainer');

                let currentQuestions = [];
                let totalTime = 0;
                let timerInterval = null;
                
                    // ============================================
                    // LOAD UNITS + SECTIONS (FROM JSON)
                    // ============================================
                
                    $.get(apiBase + "/topics", function(data) {
                        console.log("Topics response:", data);
                    
                        unitContainer.empty();
                    
                        data.topics.forEach(function(topic) {
                        
                            const unitBlock = $('<div style="margin-bottom:10px;">');
                        
                            const unitCheckbox = $(`
                                <label>
                                    <input type="checkbox" class="unit-checkbox" value="${topic.unit}">
                                    <strong>${topic.unit}</strong>
                                </label>
                            `);
                            
                            unitBlock.append(unitCheckbox);
                            
                            topic.sections.forEach(function(sectionObj) {
                            
                                const sectionCheckbox = $(`
                                    <div style="margin-left:20px;">
                                        <label>
                                            <input type="checkbox"
                                                   class="section-checkbox"
                                                   data-unit="${topic.unit}"
                                                   value="${sectionObj.section}">
                                            ${sectionObj.section} - ${sectionObj.title}
                                        </label>
                                    </div>
                                `);
                                
                                unitBlock.append(sectionCheckbox);
                            });
                        
                            unitContainer.append(unitBlock);
                        });
                    
                    });
                
                    // ============================================
                    // UNIT CHECKBOX → SELECT ALL SECTIONS
                    // ============================================
                
                    $(document).on('change', '.unit-checkbox', function() {
                    
                        const unit = $(this).val();
                        const isChecked = $(this).is(':checked');
                    
                        $(`.section-checkbox[data-unit="${unit}"]`)
                            .prop('checked', isChecked);
                    });
                
                    // ============================================
                    // SECTION CHECKBOX → AUTO UPDATE UNIT CHECKBOX
                    // ============================================
                
                    $(document).on('change', '.section-checkbox', function() {
                    
                        const unit = $(this).data('unit');
                    
                        const totalSections =
                            $(`.section-checkbox[data-unit="${unit}"]`).length;
                    
                        const checkedSections =
                            $(`.section-checkbox[data-unit="${unit}"]:checked`).length;
                    
                        if (checkedSections === totalSections) {
                            $(`.unit-checkbox[value="${unit}"]`)
                                .prop('checked', true);
                        } else {
                            $(`.unit-checkbox[value="${unit}"]`)
                                .prop('checked', false);
                        }
                    });
                
                    // ============================================
                    // GENERATE QUIZ
                    // ============================================
                
                    $('#generateQuizBtn').off('click').on('click', function() {
                    
                        let selectedUnits = [];
                        let selectedSections = [];
                    
                        $('.unit-checkbox:checked').each(function() {
                            selectedUnits.push($(this).val());
                        });
                    
                        $('.section-checkbox:checked').each(function() {
                            selectedSections.push({
                                unit: $(this).data('unit'),
                                section: $(this).val()
                            });
                        });
                    
                        if (selectedUnits.length === 0 && selectedSections.length === 0) {
                            alert("Select at least one unit or section.");
                            return;
                        }
                    
                        if (selectedUnits.length > 3) {
                            alert("Maximum 3 units allowed.");
                            return;
                        }
                    
                        const numQuestions = parseInt($('#questionCount').val());
                        const difficulty = $('#difficulty').val();
                    
                        if (!numQuestions || numQuestions <= 0) {
                            alert("Enter valid number of questions.");
                            return;
                        }
                    
                        // ============================
                        // TIMER CALCULATION
                        // ============================
                    
                        let timePerQuestion = 60; // easy
                    
                        if (difficulty === 'medium') timePerQuestion = 120;
                        if (difficulty === 'hard') timePerQuestion = 150;
                    
                        totalTime = numQuestions * timePerQuestion;
                    
                        quizContainer.html("<p>Generating quiz...</p>");
                    
                        $.ajax({
                            url: apiBase + "/generate-quiz",
                            method: "POST",
                            contentType: "application/json",
                            data: JSON.stringify({
                                units: selectedUnits,
                                sections: selectedSections,
                                num_questions: numQuestions,
                                difficulty: difficulty
                            }),
                            success: function(data) {
                            
                                console.log("Quiz API response:", data);

                                if (!data || data.ok !== true) {
                                    quizContainer.html("<p>" + (data.error || "Quiz generation failed.") + "</p>");
                                    return;
                                }
                            
                                if (!Array.isArray(data.questions) || data.questions.length === 0) {
                                    quizContainer.html("<p>No questions generated.</p>");
                                    return;
                                }
                            
                                // currentQuestions = data.questions;
                            
                                // renderQuiz(data.questions);
                                // startTimer();
                                // enableExamProtection();
                                // Redirect to attempt page via POST
                                const form = $('<form>', {
                                    method: 'POST',
                                    action: M.cfg.wwwroot + '/local/automation/student_quiz_attempt.php'
                                });

                                form.append(`<input type="hidden" name="sesskey" value="${M.cfg.sesskey}">`);
                                form.append(`<input type="hidden" name="courseid" value="${courseid}">`);
                                form.append(`<input type="hidden" name="difficulty" value="${difficulty}">`);
                                form.append(`<input type="hidden" name="totalquestions" value="${data.questions.length}">`);
                                form.append(`<input type="hidden" name="quizjson" value='${JSON.stringify(data.questions)}'>`);

                                // Add selected topics
                                selectedUnits.forEach(function(u) {
                                    form.append(`<input type="hidden" name="topics[]" value="${u}">`);
                                });

                                $('body').append(form);
                                form.submit();
                            },
                            error: function() {
                                quizContainer.html("<p>Server error.</p>");
                            }
                        });
                    });
                
                    // ============================================
                    // RENDER QUIZ
                    // ============================================
                
                    function renderQuiz(questions) {
                    
                        clearInterval(timerInterval);
                    
                        quizContainer.empty();
                    
                        const timerDiv = $('<div id="timer" style="position:fixed; top:20px; right:20px; font-weight:bold; font-size:18px;"></div>');
                        quizContainer.append(timerDiv);
                    
                        const form = $('<form id="quizForm">');
                    
                        questions.forEach(function(q, index) {
                        
                            const block = $('<div class="question-block" style="margin-bottom:20px;">');
                        
                            block.append(`<p><strong>Q${index + 1}:</strong> ${q.question}</p>`);
                        
                            q.options.forEach(function(optionText, optIndex) {
                            
                                const label = $(`
                                    <label>
                                        <input type="radio" name="q${index}" value="${optIndex}">
                                        ${optionText}
                                    </label><br>
                                `);
                                
                                block.append(label);
                            });
                        
                            block.attr("data-correct", q.answer_index);
                        
                            form.append(block);
                        });
                    
                        const submitBtn = $('<button type="button">Submit Quiz</button>');
                    
                        submitBtn.on('click', function() {
                            submitQuiz(form);
                        });
                    
                        form.append(submitBtn);
                        quizContainer.append(form);
                    }
                
                    // ============================================
                    // TIMER
                    // ============================================
                
                    function startTimer() {
                    
                        updateTimerDisplay();
                    
                        timerInterval = setInterval(function() {
                        
                            totalTime--;
                        
                            updateTimerDisplay();
                        
                            if (totalTime <= 0) {
                                clearInterval(timerInterval);
                                alert("Time is up!");
                                submitQuiz($('#quizForm'));
                            }
                        
                        }, 1000);
                    }
                
                    function updateTimerDisplay() {
                    
                        let minutes = Math.floor(totalTime / 60);
                        let seconds = totalTime % 60;
                    
                        $('#timer').text(
                            String(minutes).padStart(2, '0') + ":" +
                            String(seconds).padStart(2, '0')
                        );
                    }
                
                    // ============================================
                    // SUBMIT QUIZ
                    // ============================================
                
                    function submitQuiz(form) {
                    
                        clearInterval(timerInterval);
                    
                        let score = 0;
                    
                        form.find(".question-block").each(function() {
                        
                            const selected = $(this).find("input:checked");
                            const correct = parseInt($(this).attr("data-correct"));
                        
                            if (selected.length &&
                                parseInt(selected.val()) === correct) {
                                score++;
                            }
                        });
                    
                        quizContainer.html(`
                            <h3>Quiz Completed</h3>
                            <p>Your Score: ${score} / ${currentQuestions.length}</p>
                        `);
                    }
                
                    // ============================================
                    // BASIC EXAM PROTECTION
                    // ============================================
                
                    function enableExamProtection() {
                    
                        $(document).on('contextmenu', function(e) {
                            e.preventDefault();
                        });
                    
                        $(document).on('copy', function(e) {
                            e.preventDefault();
                        });
                    
                        $(document).on('keydown', function(e) {
                            if (e.ctrlKey) {
                                e.preventDefault();
                            }
                        });
                    
                        history.pushState(null, null, location.href);
                        window.onpopstate = function () {
                            history.go(1);
                        };
                    }
      
            }); 

        }
    };
});