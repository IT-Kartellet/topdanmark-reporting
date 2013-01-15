SELECT mdl_question.questiontext, mdl_question_states.grade, mdl_question_states.attempt, MAX(mdl_question_states.attempt)
FROM mdl_user, mdl_quiz_attempts, mdl_question_states, mdl_quiz_question_instances, mdl_question 
WHERE mdl_question.id = mdl_question_states.question
AND mdl_question.id = mdl_quiz_question_instances.question
AND mdl_quiz_question_instances.quiz = 11
AND mdl_question_states.event IN (3,6,9)
AND mdl_user.username = 'admin'
AND mdl_user.id = mdl_quiz_attempts.userid
AND mdl_quiz_attempts.uniqueid = mdl_question_states.attempt
GROUP BY mdl_question.questiontext, mdl_question_states.grade, mdl_question_states.attempt
