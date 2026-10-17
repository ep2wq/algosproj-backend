# Arabic glossary and translation rules

For the Arabic course text in this folder (`modules.php`, `lessons.php`, `problems.php`).
Pilot: the first module, `basics` ("Getting started" = البداية). To be reviewed by a native
speaker before the other modules are translated.

Confidence: **settled** (the normal Arabic CS term), **common** (widely used, alternatives
exist), **my choice** (a decision; please confirm or change). Rows marked ⚠ need your decision.

## Rule 1: the interface wins

A term the interface already uses (`algosproj-frontend/src/locales/ar.json`) is used the same
way in course text. Never introduce a second word for it.

| English | Arabic (interface) |
|---|---|
| Lessons / lesson | الدروس / درس |
| Challenges / challenge | التحديات / تحدٍّ |
| Leaderboard | لوحة الصدارة |
| points | نقاط |
| problem (exercise) | مسألة |
| module | وحدة |
| topic | موضوع |
| Input / Expected output | المدخلات / المخرجات المتوقعة |
| test, hidden test | اختبار، اختبار مخفي |
| Examples | أمثلة |
| Submit | إرسال |
| code | كود |
| editor | المحرر |
| Easy / Medium / Hard | سهل / متوسط / صعب |
| Solved | تم الحل |
| class (Java) | class, kept in English (the interface says "الـ class"; course text avoids the tatweel: "اسم class") |

## Rule 2: how terms are written

- A term without a settled Arabic form: Arabic, then the English in parentheses at its first
  mention in each lesson, e.g. "المصفوفة (Array)". Afterwards Arabic only.
- Established English terms stay English: language names (Python, Java, JavaScript), `C++`,
  `int`, `long`, class.
- Every identifier, keyword, function, Big-O expression, variable, value and literal output is
  inline code: `best`, `O(n)`, `n = 0`, `-7`, `YES`. Inline code is shown left to right inside
  Arabic text; bare Latin with digits or operators (C++, -7, 2 * n) is not allowed in Arabic prose
  because its order breaks in right-to-left text. Even a heading is `## \`C++\``.
- Plain quantities in words may stay bare Western digits: "32 بت", "السطر 1".
- Arabic punctuation (، ؛ ؟), Western digits (0-9) only, no tatweel (ـ), so no "الـ" or "بـ".
- Code blocks are byte-identical to the English. Same headings, lists and code blocks in the same
  order. Example values, numbers and expected outputs stay identical.
- Natural Modern Standard Arabic for university students, short sentences, addressing the
  reader ("اقرأ"، "اطبع")، the same teaching tone; not word for word. Each body within about
  ±25% of the English word count.
- Problem statements: same meaning, numbers, limits and output format. Section names: المدخلات،
  المخرجات، القيود. Ranges "a <= x <= b" stay inline code, or are written "بين `a` و`b`، شاملًا
  الطرفين" (never split one expression into several code pieces).
- Each batch is checked by a back-translation from a reader who has not seen the English.

## Terms used in the pilot

| English | Arabic | Confidence | Note |
|---|---|---|---|
| array | المصفوفة (Array) | common ⚠ | Also the usual word for "matrix" (مصفوفة ثنائية الأبعاد). Alternative: "مصفوفة أحادية" or "قائمة". Decide before the arrays module. |
| element | عنصر | settled | |
| position (index) | موضع | my choice ⚠ | "فهرس" or "مؤشر" for index when the word "index" appears in later modules. |
| integer | عدد صحيح | settled | |
| signed integer | عدد صحيح ذو إشارة | common | |
| 32-bit / 64-bit | بطول 32 بت / 64 بت | common | |
| overflow | تجاوز السعة (overflow) | common | "فيض" is also used. |
| string | سلسلة نصية (string) | common | |
| text | نص | settled | |
| variable | متغير | settled | |
| loop | حلقة تكرار (loop) | common | |
| exception | استثناء (exception) | settled | |
| complexity | التعقيد | settled | |
| time (complexity line) | الوقت | my choice ⚠ | Alternative "الزمن" or "التعقيد الزمني". |
| space (complexity line) | الذاكرة | my choice ⚠ | Alternative "المساحة" or "التعقيد المكاني". |
| standard input / output | الإدخال القياسي / الإخراج القياسي (standard input / output) | common | |
| stream | قناة (stream) | my choice ⚠ | "مجرى" is also used. |
| grader | نظام التصحيح (grader) | my choice ⚠ | Alternative "المصحِّح الآلي". |
| whitespace | المسافات البيضاء (whitespace) | common | |
| expected answer | الإجابة المتوقعة | settled | |
| prompt ("Enter a number:") | رسالة | my choice | Paraphrased, no term needed. |
| snippet | مقتطف كود | my choice | |
| debugging | تتبّع الأخطاء (debugging) | my choice ⚠ | Alternative "تصحيح الأخطاء"; avoid confusion with "نظام التصحيح" (grader). |
| running total | مجموع تراكمي (running total) | common | |
| single pass | مرور واحد (single pass) | my choice ⚠ | Alternative "تمريرة واحدة". |
| sorting | الترتيب (sorting) | common ⚠ | "الفرز" is also common. Decide before the sorting module. |
| range | المدى | common | |
| maximum value | القيمة العظمى / أكبر عنصر | common | |
| constraints | القيود | common | |
| inclusive (both ends) | شاملًا الطرفين | common | |
| Warm-up | الإحماء | my choice | Lesson title. |
| Getting started | البداية | my choice | Module title. |
