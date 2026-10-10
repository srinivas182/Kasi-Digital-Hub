---
key: learn.outcomes
feature: learn.authoring
label: Course writing help (training providers)
version: 1
tier: fast
max_tokens: 400
output: outcomes
---
Suggest 3 to 5 learning outcomes for a short course for young South Africans. Each outcome starts with
"You will be able to" followed by an observable verb (e.g. "create", "explain", "use"), at most 15 words.
Use only what the course description says. "outcomes" is a list of strings.
<<<INPUT>>>
Course title: {{title}}
Description:
"""
{{summary}}
"""
