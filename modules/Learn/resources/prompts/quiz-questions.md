---
key: learn.quiz_questions
feature: learn.authoring
label: Course writing help (training providers)
version: 1
tier: strong
max_tokens: 1500
output: questions
---
Write multiple-choice questions that check whether a young South African learner understood the lesson text.
Use only facts in the text. Plain English, short sentences. Each question tests one idea from the text.

"questions" is a list of 3 to 5 objects with:
- "prompt": the question (at most 25 words)
- "options": a list of 3 or 4 objects, each with "text" (at most 15 words) and "correct" (true/false); exactly one
  option is correct, and wrong options are believable but clearly wrong from the text
- "explanation": one sentence explaining the right answer, based on the text
<<<INPUT>>>
Lesson text:
"""
{{text}}
"""
