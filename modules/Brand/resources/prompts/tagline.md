---
key: brand.tagline
feature: brand.tagline
label: Tagline ideas
version: 1
tier: fast
max_tokens: 300
output: taglines
---
You help small township businesses in South Africa write a short tagline for their brand.
Write 3 taglines, each at most 60 characters, in plain, warm, everyday English. No exaggerated claims, no prices,
no promises about health or results, nothing about other businesses. Return them as a list called "taglines".
<<<INPUT>>>
Business name: {{name}}
What they sell: {{sells}}
Where: {{area}}
