<?php

// Arabic lesson text (lessons.title_ar, body_ar), keyed by lesson slug. English is in
// ../lessons.php and ../lesson_bodies.php. Same headings, lists and code blocks in the same order;
// code blocks byte-identical to the English.
// Arabic keeps technical terms in English: ~term~ in prose, `inline code` for code and values.
// Rules and terms: GLOSSARY.md in this folder and the README ("Content languages").

return [
    'warm-up' => [
        'title' => 'الإحماء',
        'body' => <<<'TEXT'
يتواصل برنامجك مع نظام التصحيح عبر اثنين من الـ~streams~. يكتب نظام التصحيح الـ~input~ الخاص بالاختبار في الـ~standard input~، ثم يشغّل برنامجك، ويقرأ ما يطبعه في الـ~standard output~. يُقارن هذا النص بالإجابة المتوقعة. يُتجاهل الـ~whitespace~ في البداية والنهاية، أما كل ما عدا ذلك فيجب أن يطابقها تمامًا.

## مثال
الـ~input~ هو السطر `21`. يقرأ برنامجك النص `"21"`، ويحوّله إلى العدد الصحيح `21`، ثم يضاعفه إلى `42` ويطبع `42`. يقارن نظام التصحيح `"42"` بالقيمة المتوقعة `"42"`، ويعدّ الاختبار ناجحًا.

## طريقة التفكير في الحل
لكل حل ثلاثة أجزاء: اقرأ، ثم احسب، ثم اطبع. يصل الـ~input~ على شكل نص، لذا حوّله إلى أعداد قبل أي عملية حسابية. اطبع الإجابة فقط. أي رسالة مثل `"Enter a number:"` تصبح جزءًا من الـ~output~ وتُفشل الاختبار.

كل مقتطف كود أدناه يقرأ سطرًا واحدًا من الأعداد ويطبع عدد الأعداد الزوجية فيه. احتفظ بجزأي القراءة والطباعة، واستبدل الجزء الأوسط.

## ~Python~
    nums = list(map(int, input().split()))
    print(sum(1 for x in nums if x % 2 == 0))

## ~Java~ (يجب أن يكون اسم الـ~class~ هو `Main`)
    import java.util.Scanner;

    public class Main {
        public static void main(String[] args) {
            Scanner in = new Scanner(System.in);
            String[] parts = in.nextLine().trim().split(" ");
            int evens = 0;
            for (String p : parts) {
                if (Integer.parseInt(p) % 2 == 0) evens++;
            }
            System.out.println(evens);
        }
    }

## ~JavaScript~
    const input = require('fs').readFileSync(0, 'utf8');
    const nums = input.trim().split(/\s+/).map(Number);
    console.log(nums.filter((x) => x % 2 === 0).length);

## ~C++~
    #include <iostream>
    using namespace std;

    int main() {
        int x, evens = 0;
        while (cin >> x) {
            if (x % 2 == 0) evens++;
        }
        cout << evens << endl;
    }

## الـ~complexity~
الـ~time complexity~: `O(1)` للمسألة أدناه، لأن هناك عددًا واحدًا وعملية ضرب واحدة. أما المقتطفات أعلاه فتقرأ سطرًا كاملًا من `n` عددًا، وهذا يستغرق `O(n)`.
الـ~space complexity~: `O(1)` للمسألة أدناه، لأن الحل يحتفظ بقيمة واحدة فقط. أما المقتطفات فتخزن السطر، `O(n)`، ما عدا مقتطف ~C++~ الذي يقرأ عددًا واحدًا في كل مرة.

## انتبه إلى
- الأعداد السالبة والصفر: ضعف `-7` هو `-14`.
- أكبر قيم الـ~input~: الناتج `2 * 1000000000 = 2000000000` ما زال يتسع في `int` بحجم ~32-bit~، وحده الأقصى `2147483647`.
- أوامر الطباعة المتبقية من الـ~debugging~. إنها جزء من الـ~output~ أيضًا.

جرّب دورة القراءة ثم الحساب ثم الطباعة على المسألة أدناه، باللغة التي اخترتها للحل.
TEXT,
    ],
    'sum-of-an-array' => [
        'title' => 'مجموع عناصر الـ array',
        'body' => <<<'TEXT'
الـ~array~ مجموعة من القيم المخزنة واحدة تلو الأخرى، ولكل قيمة موضع مرقّم يبدأ من `0`. لجمع هذه القيم، استخدم ~running total~، كما تجمع آلة الدفع في المتجر سعر كل سلعة لحظة مسحها.

## مثال
الـ~input~ هو `n = 5` والأعداد `10 -2 7 0 5`.
ابدأ من `total = 0`.
أضف `10`: `total = 10`.
أضف `-2`: `total = 8`.
أضف `7`: `total = 15`.
أضف `0`: `total = 15`.
أضف `5`: `total = 20`.
اطبع `20`.

## طريقة التفكير في الحل
اقرأ `n` أولًا، ثم اقرأ `n` عددًا بالضبط. ابدأ المجموع من `0` لا من العدد الأول، حتى يعمل الحل مع الـ~array~ الفارغ أيضًا. أضف كل عدد مرة واحدة. لا حاجة إلى الأعداد بعد إضافتها، لذا يمكنك إضافة كل عدد فور قراءته بدلًا من تخزين الـ~array~ كله. هذا النمط، ~variable~ واحد يتغير خلال ~single pass~، يتكرر في كثير من المسائل اللاحقة.

## الـ~complexity~
الـ~time complexity~: `O(n)`، لأن كل عدد يُقرأ ويُضاف مرة واحدة.
الـ~space complexity~: `O(1)` إضافية عند إضافة كل عدد فور قراءته؛ و`O(n)` عند تخزين الأعداد كلها أولًا. كلا الحلين مقبول بهذا الحجم.

## انتبه إلى
- الحالة `n = 0`. يكون السطر الثاني فارغًا والإجابة `0`. في ~Java~، يُرجع `"".split(" ")` ~array~ فيه ~string~ فارغ واحد، وتحويله إلى عدد يرمي ~exception~. قراءة `n` عددًا بالضبط باستخدام ~loop~ تتجنب ذلك.
- الأعداد السالبة. إنها تُنقص المجموع. لا تحتاج إلى معالجة خاصة، لكن اختبر الحل ببعضها، مثل `-5 -5 3 3 -1 0` التي مجموعها `-5`.
- الحجم. أكبر مجموع ممكن هو `1000 * 1000000 = 1000000000`، وهو يتسع في `int` بحجم ~32-bit~. المسائل ذات الحدود الأكبر تحتاج إلى ~type~ بحجم ~64-bit~: `long` في ~Java~ و`long long` في ~C++~. الأعداد الصحيحة في ~Python~ لا يحدث فيها ~overflow~ أبدًا، وأعداد ~JavaScript~ دقيقة حتى نحو `9 * 10^15`.

اكتب حل الـ~running total~ للمسألة أدناه، وتحقق من الحالة `n = 0` قبل الإرسال.
TEXT,
    ],
    'maximum-of-an-array' => [
        'title' => 'أكبر عنصر في الـ array',
        'body' => <<<'TEXT'
لإيجاد أكبر عدد، يكفي تذكّر شيء واحد أثناء المرور على الأعداد: أكبر قيمة ظهرت حتى الآن. كل عدد جديد إما أن يتجاوزها أو لا.

## مثال
الـ~input~ هو `3 1 4 1 5 2`.
ابدأ من `best = 3`، وهو العدد الأول.
`1` ليس أكبر من `3`، لذا تبقى قيمة `best` هي `3`.
`4` أكبر من `3`، لذا تصبح قيمة `best` هي `4`.
`1` ليس أكبر من `4`، لذا تبقى قيمة `best` هي `4`.
`5` أكبر من `4`، لذا تصبح قيمة `best` هي `5`.
`2` ليس أكبر من `5`، لذا تبقى قيمة `best` هي `5`.
اطبع `5`.

## طريقة التفكير في الحل
اجعل العدد الأول قيمة `best` الابتدائية، ثم قارن به كل عدد آخر مرة واحدة. لا تستبدل `best` إلا عندما يكون العدد أكبر منها. عند تساوي القيم لا يهم أيها يُحتفظ به، لأن المطبوع هو القيمة لا موضعها. كما في المجموع، هذا ~single pass~، لذا يمكنك مقارنة كل عدد فور قراءته دون تخزين الـ~array~ أبدًا. إجراء ~sorting~ على الـ~array~ ثم أخذ آخر عنصر فيه يعطي الإجابة الصحيحة أيضًا، لكنه يؤدي عملًا أكثر بكثير مما يلزم.

## الـ~complexity~
الـ~time complexity~: `O(n)`، لأن كل عدد يُقارن مرة واحدة.
الـ~space complexity~: `O(1)` إضافية، لأن الحل لا يحتفظ إلا بقيمة `best` (إضافة إلى الـ~input~ إذا خُزّن).

## انتبه إلى
- أن تكون كل الأعداد سالبة. في `-8 -3 -9 -3 -12` الإجابة هي `-3`. إذا بدأت `best` من `0` فسيُطبع `0`، وهو ليس في الـ~array~ أصلًا. ابدأ من العنصر الأول، أو من أصغر قيمة ممكنة: `Integer.MIN_VALUE` في ~Java~، و`INT_MIN` في ~C++~، و`-Infinity` في ~JavaScript~، و`float('-inf')` في ~Python~.
- عدد واحد فقط. عندما `n = 1` لا يوجد ما يُقارن، وذلك العدد هو الإجابة.
- المدى. تتراوح القيم من `-1000000000` إلى `1000000000`. كلا الطرفين يتسع في `int` بحجم ~32-bit~، لذا يكفي `int` في ~Java~ و~C++~.
- تكرار القيمة العظمى. يظهر `-3` مرتين في `-8 -3 -9 -3 -12` أعلاه؛ ومع ذلك تبقى الإجابة `-3` فقط، وتُطبع مرة واحدة.

حُلّ المسألة أدناه في ~single pass~، دون ~sorting~.
TEXT,
    ],
    'even-or-odd' => [
        'title' => 'زوجي أم فردي',
        'body' => <<<'TEXT'
يصل الـ~input~ على شكل نص، لذا حوّله أولًا إلى عدد. في ~Python~ يكون ذلك `int(input())`. في ~Java~، استخدم `Scanner` و`nextInt()`. في ~C++~، `cin >> n`. في ~JavaScript~، اقرأ السطر واستدعِ `Number` عليه.

يكون العدد زوجيًا عندما ينقسم إلى نصفين صحيحين متساويين. الـ~remainder operator~، `%`، يعطي ما يتبقى بعد القسمة: `7 % 2` يساوي `1`، لأن `7 = 3 * 2 + 1`. لذا يكون `n` زوجيًا إذا وفقط إذا كان `n % 2` يساوي `0`.

## مثال
`n = 7`: `7 % 2` يساوي `1` لا `0`، لذا اطبع `odd`.
`n = 0`: `0 % 2` يساوي `0`، لذا اطبع `even`. الصفر عدد زوجي.
`n = -3`: هنا تختلف اللغات. ~Python~ تعطي `-3 % 2 = 1`. أما ~Java~ و~C++~ و~JavaScript~ فتعطي `-1`.

## طريقة التفكير في الحل
يأتي الفرق من طريقة تقريب كل لغة لناتج القسمة. الناتج الدقيق لقسمة `-3` على `2` هو `-1.5`. ~Python~ تقرّبه إلى الأسفل، أي إلى `-2`، كما يفعل `-3 // 2`، فيكون الباقي `1`: `-3 = -2 * 2 + 1`. أما ~Java~ و~C++~ و~JavaScript~ فتقرّب نحو الصفر، أي إلى `-1`، فيكون الباقي `-1`: `-3 = -1 * 2 - 1`.

تتفق القاعدتان على أمر واحد: العدد الزوجي يترك دائمًا الباقي `0`. لذا اختبر `n % 2 == 0` لمعرفة الزوجي، وكل ما عدا ذلك فردي. هذا يعمل مع الأعداد الموجبة والسالبة والصفر، في كل لغة.

## الـ~complexity~
الـ~time complexity~: `O(1)`، عملية قسمة واحدة.
الـ~space complexity~: `O(1)`.

## انتبه إلى
- اختبار `n % 2 == 1` لمعرفة الفردي. في ~Java~ و~C++~ و~JavaScript~، `-3 % 2` يساوي `-1`، فيظهر `-3` زوجيًا. في ~Python~ ينجح الاختبار نفسه مصادفة.
- حجم `n`. القيمة `1,000,000,000` تتسع في `int` بحجم ~32-bit~، وأكبر قيمة فيه `2,147,483,647`، لذا يكفي `int` هنا.
- الكلمتان بالضبط: `even` و`odd`، بأحرف صغيرة.

نظام التصحيح لا يرى إلا الكلمة، فلا يمكنه معرفة هل استخدمت `%` أم حيلة أخرى. استخدم `%`: فهو يتكرر في كل المسائل تقريبًا.
TEXT,
    ],
    'fizzbuzz' => [
        'title' => 'FizzBuzz',
        'body' => <<<'TEXT'
الـ~loop~ يكرر جزءًا من الكود. الـ~for loop~ ينفّذه مرة لكل قيمة يأخذها الـ~counter~. في ~Python~، يعطي `for i in range(1, n + 1)` القيم `i = 1, 2, ..., n`. وفي ~Java~ و~C++~ و~JavaScript~ يفعل `for (i = 1; i <= n; i++)` الشيء نفسه.

يكون العدد من مضاعفات `3` عندما لا تترك قسمته على `3` أي باقٍ: `i % 3 == 0`. والأمر نفسه مع `5`.

## مثال
خذ `n = 15`. الأسطر هي:
`1, 2, Fizz, 4, Buzz, Fizz, 7, 8, Fizz, Buzz, 11, Fizz, 13, 14, FizzBuzz`.
`3` و`6` و`9` و`12` من مضاعفات `3`. و`5` و`10` من مضاعفات `5`. و`15` من مضاعفات الاثنين، لذا يأخذ `FizzBuzz`.

## طريقة التفكير في الحل
داخل الـ~loop~، استخدم سلسلة `if` و`else if` و`else`. يفحص الكمبيوتر الـ~conditions~ من الأعلى، وينفّذ فقط أول واحد منها يكون صحيحًا.

لهذا تأتي حالة `FizzBuzz` أولًا. العدد الذي هو من مضاعفات `3` و`5` معًا هو أيضًا من مضاعفات `3`. إذا جاء فحص `3` أولًا، فإن `15` يطابقه ويطبع `Fizz`، ولا يُوصل إلى سطر `FizzBuzz` أبدًا. لذا افحص `i % 15 == 0` أولًا: مضاعفات `3` و`5` معًا هي بالضبط مضاعفات `15`. ثم افحص `3`، ثم `5`، وإلا فاطبع `i`.

وهناك طريقة أخرى تعمل أيضًا: ابدأ بكلمة فارغة، وأضف `Fizz` لمضاعفات `3`، وأضف `Buzz` لمضاعفات `5`، واطبع `i` إذا بقيت الكلمة فارغة.

## الـ~complexity~
الـ~time complexity~: `O(n)`، ~single pass~ على الأعداد.
الـ~space complexity~: `O(1)` إلى جانب الـ~output~.

## انتبه إلى
- المدى. ابدأ من `1` وأدرج `n` نفسه: `range(1, n)` يتوقف عند `n - 1`.
- إجابة واحدة في كل سطر، مكتوبة هكذا بالضبط: `Fizz` و`Buzz` و`FizzBuzz`.
- `n = 1`. السطر الوحيد هو `1`.

نظام التصحيح لا يقارن إلا الأسطر، فلا يمكنه معرفة أي الطريقتين استخدمت. جرّب `n = 15` أولًا وتأكد أن السطر الأخير هو `FizzBuzz`.
TEXT,
    ],
    'count-vowels' => [
        'title' => 'عدّ حروف العلة',
        'body' => <<<'TEXT'
الـ~string~ قطعة من النص، والـ~character~ حرف أو رقم أو مسافة واحدة فيه. الـ~input~ هنا سطر كامل وقد يحتوي على مسافات، لذا اقرأ السطر كله لا كلمة واحدة.
- ~Python~: `input()` يقرأ السطر كله.
- ~Java~: `nextLine()` من `Scanner` أو `readLine()` من `BufferedReader`. أما `next()` فيتوقف عند أول مسافة.
- ~C++~: `getline(cin, s)`. أما `cin >> s` فيتوقف عند أول مسافة.
- ~JavaScript~: اقرأ الـ~standard input~ كله وخذ السطر الأول.

## مثال
خذ السطر `Programming  Is   FUN`.
بالأحرف الصغيرة يصبح `programming  is   fun`.
مُرّ عليه ~character~ واحدًا في كل مرة. حروف العلة هي `o` و`a` و`i` في `programming`، و`i` في `is`، و`u` في `fun`. اطبع `5`.
السطر الفارغ، أو السطر المكوّن من مسافات فقط، لا يحتوي على حروف علة، لذا اطبع `0`.

## طريقة التفكير في الحل
استخدم ~loop~ على الـ~characters~: `for ch in s` في ~Python~، أو ~index~ من `0` إلى الطول ناقص `1` في اللغات الأخرى. قارن كل ~character~ مع `a` و`e` و`i` و`o` و`u`، وأضف `1` إلى `count` عند التطابق.

تُقارن الـ~characters~ بدقة: `A` و`a` مختلفان. لذا حوّل كل ~character~ إلى حرف صغير أولًا، باستخدام `lower()` في ~Python~، و`Character.toLowerCase` في ~Java~، و`tolower` في ~C++~، و`toLowerCase()` في ~JavaScript~. عندها تكفي خمس مقارنات للحالتين.

## الـ~complexity~
الـ~time complexity~: `O(n)`، حيث `n` طول السطر، نظرة واحدة إلى كل ~character~.
الـ~space complexity~: `O(n)` لتخزين السطر.

## انتبه إلى
- قراءة كلمة واحدة. مع `Programming  Is   FUN`، يقرأ `cin >> s` أو `next()` كلمة `Programming` فقط ويطبع `3`.
- الأحرف الكبيرة. دون التحويل إلى أحرف صغيرة، يضيع `I` و`U`، وهذا يعطي `3` أيضًا.
- السطر الفارغ. الإجابة `0`، وليست خطأ.

نظام التصحيح لا يرى إلا العدد، لذا يجتاز الاختبارات أيضًا حل يستخدم ~regular expression~ أو ~built-in function~ للعد. اكتب الـ~loop~: إنه النمط الذي تقوم عليه معظم مسائل الـ~strings~.
TEXT,
    ],
    'factorial' => [
        'title' => 'Factorial',
        'body' => <<<'TEXT'
الـ~factorial~ للعدد `n`، ويُكتب `n!`، هو `1 * 2 * 3 * ... * n`. و`0!` يساوي `1`.

الـ~function~ جزء من الكود له اسم ويمكن استدعاؤه. الـ~parameter~ هو القيمة التي تُمرَّر إليه، والـ~return value~ هو ما يعيده. هنا، `factorial(n)` يُرجع `n!`.

## مثال
`5! = 1 * 2 * 3 * 4 * 5 = 120`.
لحساب `0!`، ابدأ من `result = 1`. لا يجد الـ~loop~ ما يضربه، فتبقى `result` تساوي `1`.
`12! = 479001600`، و`13! = 6227020800`، و`20! = 2432902008176640000`.

## طريقة التفكير في الحل
باستخدام ~loop~: اجعل `result = 1`، واضربه في كل `i` من `2` إلى `n`، ثم أرجِعه.
باستخدام ~recursion~، يستدعي الـ~function~ نفسه: `factorial(n) = n * factorial(n - 1)`، و`factorial(0) = 1` هي نقطة التوقف.

الجزء الصعب هو الحجم. لكل ~type~ رقمي قيمة قصوى، وتجاوزها يسمى ~overflow~.
- `int` بحجم ~32-bit~، وهو الافتراضي في ~Java~ و~C++~، يتسع حتى `2,147,483,647`. يتسع فيه `12!`، أما `13!` فلا. في ~Java~ تلتف القيمة بصمت ويُطبع `1932053504`؛ وفي ~C++~ تكون النتيجة ~undefined~، وفي الـ~sandbox~ لدينا طُبعت القيمة نفسها.
- `long` في ~Java~ و`long long` في ~C++~ يتسعان حتى نحو `9.2 * 10^18`، لذا يتسع `20!`.
- الأعداد الصحيحة في ~Python~ تكبر حسب الحاجة.
- `Number` في ~JavaScript~ يخزن الأعداد الصحيحة بدقة حتى `9,007,199,254,740,991` فقط. و`19!` أكبر من ذلك، لذا يمكن عمومًا أن تُقرَّب العمليات الحسابية دون تحذير (لكن ذلك لا يحدث هنا). استخدم `BigInt` بدلًا منه: اكتب `1n`، وحوّل باستخدام `BigInt(n)`، ولن يحدث تقريب أبدًا.

## الـ~complexity~
الـ~time complexity~: `O(n)` عملية ضرب.
الـ~space complexity~: `O(1)` مع الـ~loop~، و`O(n)` مع استدعاءات الـ~recursion~.

## انتبه إلى
- `int` بدلًا من `long`. `13` هو أول ~input~ يجعله يفشل.
- `Number` العادي في ~JavaScript~. لكل `n` هنا، حتى `20!`، تخرج النتائج صحيحة مصادفة، لذا لا تستطيع الاختبارات كشف المشكلة. استخدم `BigInt` لتكون الإجابة صحيحة عن قصد، لا مصادفة.

لا يستطيع نظام التصحيح معرفة هل كتبت ~function~ أم ~loop~ أم ~recursion~، وجدول يحوي الإجابات وعددها `21` سيجتاز الاختبارات أيضًا. اكتب الـ~function~: الدروس التالية تبني عليه.
TEXT,
    ],
    'caesar-cipher' => [
        'title' => 'Caesar cipher',
        'body' => <<<'TEXT'
الـ~string~ سلسلة من الـ~characters~، لكل منها موضع يبدأ من `0` ورمز رقمي: رمز `a` هو `97`، ورمز `A` هو `65`، وتتبع بقية حروف كل أبجدية بالترتيب.

في ~Python~ و~Java~ و~JavaScript~، الـ~strings~ ~immutable~: كل تغيير ينشئ ~string~ جديدًا. ابنِ النتيجة بجمع الأجزاء ثم ربطها مرة واحدة، أو باستخدام ~builder~. أما في ~C++~ فالـ~strings~ قابلة للتغيير، لذا يضيف `+=` إلى نهايتها.

## مثال
خذ `k = 3` و`Hello, World!`. ينتقل `H` ثلاثة أحرف إلى الأمام فيصبح `K`، و`e` يصبح `h`، و`l` يصبح `o`. تبقى الفاصلة والمسافة و`!` كما هي. اطبع `Khoor, Zruog!`.
مع `k = 1`، يعود `z` إلى `a` ويعود `Z` إلى `A`. ومع `k = 1000`، لا يهم إلا `1000 % 26 = 12`، لذا يصبح `z` هو `l`.

## طريقة التفكير في الحل
للحرف الصغير أو الكبير، حوّله إلى عدد من `0` إلى `25`، وأضف `k`، وخذ باقي القسمة على `26`، ثم أعده حرفًا: `(c - 'a' + k) % 26 + 'a'`. باقي القسمة يعيد `z` إلى `a`، والأساس الصحيح، `'a'` أو `'A'`، يحافظ على حالة الحرف. انسخ كل ما عدا ذلك دون تغيير.

## ~Python~
    s = "Hello, World"
    print(s[0:5], s.lower())         # Hello hello, world
    print("-".join(s.split(", ")))   # Hello-World
    out = []
    for ch in s:
        out.append(chr(ord(ch) + 1) if ch.isalpha() else ch)
    print("".join(out))              # Ifmmp, Xpsme

## ~Java~
    String s = "Hello, World";
    System.out.println(s.substring(0, 5) + " " + s.toLowerCase());  // Hello hello, world
    System.out.println(String.join("-", s.split(", ")));            // Hello-World
    StringBuilder out = new StringBuilder();
    for (char ch : s.toCharArray()) out.append(Character.isLetter(ch) ? (char) (ch + 1) : ch);
    System.out.println(out);                                        // Ifmmp, Xpsme

## ~JavaScript~
    const s = 'Hello, World'
    console.log(s.slice(0, 5), s.toLowerCase())  // Hello hello, world
    console.log(s.split(', ').join('-'))         // Hello-World
    let out = ''
    for (const ch of s) out += /[a-z]/i.test(ch) ? String.fromCharCode(ch.charCodeAt(0) + 1) : ch
    console.log(out)                             // Ifmmp, Xpsme

## ~C++~
    string s = "Hello, World";
    cout << s.substr(0, 5) << "\n";  // Hello (substr takes a start and a length)
    string out;
    for (char ch : s) out += isalpha(ch) ? char(ch + 1) : ch;
    cout << out << "\n";             // Ifmmp, Xpsme

لا يوجد `split` في ~C++~؛ استخدم `stringstream`.

## الـ~complexity~
الـ~time complexity~: `O(n)`، حيث `n` طول النص، خطوة واحدة لكل ~character~.
الـ~space complexity~: `O(n)` للنتيجة.

## انتبه إلى
- صغّر `k` أولًا. إضافة `1000` إلى رمز ~character~ تتجاوز `z` بكثير.
- قراءة النص. قد يكون فارغًا ويحتوي على مسافات، لذا اقرأ السطر الثاني كله.
- `s += ch` داخل ~loop~ في ~Java~ ينسخ الـ~string~ في كل مرة. أما `StringBuilder` فلا.

نظام التصحيح لا يرى إلا الـ~output~، لذا تجتاز الاختبارات أيضًا نسخة مزاحة من الأبجدية تُستخدم بوصفها ~lookup table~.
TEXT,
    ],
    'diagonal-sums' => [
        'title' => 'مجموعا القطرين',
        'body' => <<<'TEXT'
الـ~list~ يحفظ القيم بالترتيب، كل قيمة عند ~index~ يبدأ من `0`؛ وتسميه ~Java~ و~C++~ و~JavaScript~ ~array~. الـ~2D list~ هو ~list~ من الصفوف: `grid[r][c]` هي القيمة في الصف `r` والعمود `c`.

## مثال
مع `n = 3`:
    1 2 3
    4 5 6
    7 8 9
القطر الرئيسي، من أعلى اليسار إلى أسفل اليمين: `grid[0][0] + grid[1][1] + grid[2][2] = 1 + 5 + 9 = 15`.
القطر الثانوي، من أعلى اليمين إلى أسفل اليسار: `grid[0][2] + grid[1][1] + grid[2][0] = 3 + 5 + 7 = 15`.
المركز، `5`، يُحسب في المجموعين. اطبع `15 15`.

## طريقة التفكير في الحل
اقرأ `n`، ثم اقرأ كل صف في ~list~ خاص به. الـ~nested loops~ تزور كل خلية: ~loop~ خارجي على الصفوف، و~loop~ داخلي على الأعمدة. أما القطران فيكفيهما ~loop~ واحد: الصف `i` يلتقي بالقطر الرئيسي عند العمود `i`، وبالقطر الثانوي عند العمود `n - 1 - i`.

مع قيم يصل حجمها إلى `1,000,000,000` و`n` حتى `50`، قد يصل المجموع إلى `50,000,000,000`. استخدم `long` في ~Java~ و`long long` في ~C++~.

## ~Python~
    n = 3
    grid = [[0] * n for _ in range(n)]  # n separate rows
    trap = [[0] * n] * n                # n references to ONE row
    grid[0][0] = 1
    trap[0][0] = 1
    print(grid)  # [[1, 0, 0], [0, 0, 0], [0, 0, 0]]
    print(trap)  # [[1, 0, 0], [1, 0, 0], [1, 0, 0]]

## ~Java~
    int n = 3;
    long[][] grid = new long[n][n];   // every cell starts at 0
    for (int r = 0; r < n; r++)
        for (int c = 0; c < n; c++)
            grid[r][c] = r * n + c + 1;  // 1 to 9, row by row
    System.out.println(grid[1][2]);   // 6

## ~JavaScript~
    const n = 3
    const grid = Array.from({ length: n }, () => new Array(n).fill(0))  // n rows
    const trap = new Array(n).fill(new Array(n).fill(0))                // one shared row
    grid[0][0] = 1
    trap[0][0] = 1
    console.log(grid[1][0], trap[1][0])  // 0 1

## ~C++~
    int n = 3;
    vector<vector<long long>> grid(n, vector<long long>(n, 0));  // n copies of a row
    grid[0][0] = 1;
    cout << grid[1][0] << "\n";  // 0

## الـ~complexity~
الـ~time complexity~: `O(n^2)` لقراءة الشبكة، ثم `O(n)` للمجموعين.
الـ~space complexity~: `O(n^2)` لتخزين الشبكة.

## انتبه إلى
- الصف المشترك. `[[0] * n] * n` في ~Python~ و`fill` في ~JavaScript~ يضعان الصف نفسه في كل خانة، لذا يؤدي تغيير خلية إلى تغيير عمودها كله. أما ~C++~ فتنسخ الصف.
- عندما يكون `n` فرديًا، ينتمي المركز إلى القطرين معًا؛ لا تطرحه.
- المجاميع بحجم ~32-bit~ تلتف مع أكبر القيم.

الجمع أثناء القراءة دون تخزين الشبكة يجتاز الاختبارات أيضًا؛ فنظام التصحيح لا يرى إلا عددين.
TEXT,
    ],
    'word-frequency' => [
        'title' => 'تكرار الكلمات',
        'body' => <<<'TEXT'
الـ~dictionary~، أو الـ~map~، يخزن أزواجًا: كل ~key~ يقود إلى قيمة. هنا الـ~key~ هو الكلمة، والقيمة هي عدد مرات ظهورها. تسميه ~Python~ `dict`، و~Java~ `HashMap`، و~JavaScript~ `Map`، و~C++~ `map` أو `unordered_map`. البحث عن ~key~ في ~hash map~ يستغرق وقتًا `O(1)` في المتوسط.

## مثال
خذ `the cat and the hat`.
الكلمة `the` ليست في الـ~map~ بعد، لذا تأخذ `1`. ثم `cat 1`، و`and 1`، ثم `the` مرة أخرى `2`، و`hat 1`.
رتّب حسب العدد، الأكبر أولًا، وأبجديًا عند تساوي العدد:
`the 2, and 1, cat 1, hat 1`.

## طريقة التفكير في الحل
العدّ يحتاج إلى ~lookup~ مع قيمة افتراضية: الكلمة التي لم تظهر بعد تُعدّ `0`. أضف `1` وخزّن النتيجة من جديد. ثم مُرّ على الـ~entries~، وانسخها إلى ~list~ ورتّبه.

لا تطبع الـ~map~ بترتيبه الخاص. `dict` في ~Python~ و`Map` في ~JavaScript~ يحافظان على ترتيب إضافة الـ~keys~، و`HashMap` في ~Java~ ليس له ترتيب مفيد، و`map` في ~C++~ مرتب حسب الـ~key~. لا شيء من ذلك يضع الأكثر تكرارًا أولًا.

## ~Python~
    counts = {}
    for w in "the cat and the hat".split():
        counts[w] = counts.get(w, 0) + 1  # get with a default
    for w, c in sorted(counts.items(), key=lambda item: (-item[1], item[0])):
        print(w, c)  # the 2, and 1, cat 1, hat 1

## ~Java~
    Map<String, Integer> counts = new HashMap<>();
    for (String w : "the cat and the hat".split(" "))
        counts.put(w, counts.getOrDefault(w, 0) + 1);
    System.out.println(counts.get("the"));  // 2
    for (Map.Entry<String, Integer> e : counts.entrySet())
        System.out.println(e.getKey() + " " + e.getValue());  // no useful order

## ~JavaScript~
    const counts = new Map()
    for (const w of 'the cat and the hat'.split(' '))
      counts.set(w, (counts.get(w) ?? 0) + 1)  // get gives undefined, ?? turns it into 0
    for (const [w, c] of counts) console.log(w, c)  // the 2, cat 1, and 1, hat 1

## ~C++~
    map<string, int> counts;
    istringstream words("the cat and the hat");
    string w;
    while (words >> w) counts[w]++;  // a missing key starts at 0
    for (const auto& [word, c] : counts) cout << word << " " << c << "\n";  // and, cat, hat, the

## الـ~complexity~
الـ~time complexity~: `O(w)` لعدّ `w` كلمة، إضافة إلى `O(d log d)` لترتيب الكلمات المختلفة وعددها `d`.
الـ~space complexity~: `O(d)` لتخزين الـ~map~.

## انتبه إلى
- القراءة في ~C++~. `counts["dog"]` يضيف `dog` بقيمة `0` حتى لو كان القصد مجرد النظر. استخدم `counts.count(w)` للتحقق.
- التعادل. عند تساوي العدد، تُرتب الكلمات أبجديًا.

ترتيب كل الكلمات ثم عدّ المتتاليات المتساوية منها يجتاز الاختبارات أيضًا؛ فنظام التصحيح لا يستطيع معرفة هل استخدمت ~map~.
TEXT,
    ],
    'sort-the-leaderboard' => [
        'title' => 'ترتيب لوحة الصدارة',
        'body' => <<<'TEXT'
الـ~sorting~ باستخدام ~key~ يعني مقارنة قيمة محسوبة من كل عنصر بدلًا من العنصر كله. مع عدة ~keys~، قارن بالأول؛ ولا تنظر إلى الثاني إلا عند تساوي الأول. للترتيب التنازلي، اعكس ذلك الـ~key~ وحده، لا الباقي.

يكون الـ~sort~ ~stable~ عندما تحافظ العناصر المتساوية في المقارنة على ترتيبها الحالي. `sort` في ~Python~ و`List.sort` في ~Java~ و`sort` في ~JavaScript~ كلها ~stable~؛ أما `sort` في ~C++~ فليس كذلك، لكن `stable_sort` كذلك.

## مثال
`alice 50, bob 70, carol 50, dave 70`.
حسب الدرجة، الأعلى أولًا، تأتي درجات `70` قبل درجات `50`. ضمن `70` يأتي `bob` قبل `dave`؛ وضمن `50` يأتي `alice` قبل `carol`.
اطبع `bob 70, dave 70, alice 50, carol 50`.

## طريقة التفكير في الحل
إحدى الطرق مقارنة واحدة تفحص الدرجة، الأعلى أولًا، ثم الاسم. والأخرى ~two passes~ باستخدام ~stable sort~: رتّب حسب الاسم، ثم رتّب ترتيبًا ~stable~ حسب الدرجة، الأعلى أولًا. تبقى الأسماء مرتبة ضمن كل درجة.

في ~Python~، `reverse=True` يعكس الـ~key~ كله، بما في ذلك الأسماء. اجعل العدد سالبًا بدلًا من ذلك: `(-score, name)`.

الـ~comparator~ يأخذ عنصرين `a` و`b` ويحدد أيهما يأتي أولًا: في ~Java~ و~JavaScript~ العدد السالب يعني `a`، والموجب يعني `b`، و`0` يعني أنهما متساويان. وفي ~C++~ يُرجع `true` عندما يجب أن يأتي `a` قبل `b` بشكل صارم.

## ~Python~
    players = [("alice", 50), ("bob", 70), ("carol", 50), ("dave", 70)]
    players.sort(key=lambda p: (-p[1], p[0]))
    print(players)  # [('bob', 70), ('dave', 70), ('alice', 50), ('carol', 50)]

## ~Java~
    record Player(String name, int score) {}
    players.sort(Comparator.comparingInt(Player::score).reversed()
            .thenComparing(Player::name));

## ~JavaScript~
    players.sort((a, b) => b.score - a.score || (a.name < b.name ? -1 : a.name > b.name ? 1 : 0))

## ~C++~
    sort(players.begin(), players.end(), [](const Player& a, const Player& b) {
        if (a.score != b.score) return a.score > b.score;  // higher score first
        return a.name < b.name;
    });

## الـ~complexity~
الـ~time complexity~: `O(n log n)` مقارنة.
الـ~space complexity~: `O(n)` لتخزين الـ~list~.

## انتبه إلى
- ترتيب الأعداد في ~JavaScript~ دون ~comparator~: `[10, 9, 1].sort()` يقارنها كنص ويعطي `1, 10, 9`.
- إرجاع `a.score >= b.score` في ~comparator~ بلغة ~C++~. يجب أن يُرجع `false` للعناصر المتساوية، وإلا فقد يتصرف `sort` بشكل خاطئ.
- الـ~two passes~ في ~C++~ تحتاج إلى `stable_sort`.

نظام التصحيح لا يرى إلا الترتيب النهائي، فلا يمكنه التمييز بين مقارنة واحدة و~two passes~.
TEXT,
    ],
    'bank-accounts' => [
        'title' => 'الحسابات البنكية',
        'body' => <<<'TEXT'
الـ~class~ مخطط، والـ~object~ شيء واحد مبني منه. يجمع الـ~class~ بين الـ~fields~، وهي البيانات التي يحملها كل ~object~، والـ~methods~، وهي الـ~functions~ التي تستخدم تلك البيانات. يعمل الـ~constructor~ عند إنشاء ~object~ ويهيئ الـ~fields~ الخاصة به.

يقرأ البرنامج الأوامر من الـ~standard input~، والـ~classes~ التي تكتبها تؤدي العمل: يقرأ `main` كل سطر، ويجد `Account` المناسب، ويستدعي الـ~method~ الخاص به.

## مثال
`OPEN ann` يبدأ رصيدًا قيمته `0`. `DEPOSIT ann 100` ثم `WITHDRAW ann 30` يتركان `70`. `WITHDRAW ann 100` يطلب أكثر من الرصيد: اطبع `Insufficient funds` ولا تغيّر شيئًا. `BALANCE ann` يطبع `70`.

## طريقة التفكير في الحل
استخدم ~dictionary~ من الاسم إلى `Account`. لكل ~object~ رصيده الخاص، لذا لا يختلط `ann` و`bob` أبدًا.

الفخ هو الـ~shared state~. الـ~class-level field~ في ~Python~، أو الـ~static field~ في اللغات الأخرى، يتبع الـ~class~ نفسه، فتشترك كل الحسابات في عدد واحد. المقتطفات تعرض الحالتين. في ~Java~ و~C++~، توضع الأسطر التي بعد `// in main:` داخل `main`.

## ~Python~
    class Account:
        opened = 0            # class-level: one value shared by all accounts
        def __init__(self):
            self.balance = 0  # field: every object has its own
            Account.opened += 1
        def deposit(self, amount):
            self.balance += amount

    a, b = Account(), Account()
    a.deposit(50)
    print(a.balance, b.balance, Account.opened)  # 50 0 2

## ~Java~
    class Account {
        static int opened = 0;  // shared by all accounts
        long balance = 0;
        Account() { opened++; }
        void deposit(long amount) { balance += amount; }
    }
    // in main:
    Account a = new Account(), b = new Account();
    a.deposit(50);
    System.out.println(a.balance + " " + b.balance + " " + Account.opened);  // 50 0 2

## ~JavaScript~
    class Account {
      static opened = 0    // shared by all accounts
      constructor() {
        this.balance = 0
        Account.opened++
      }
      deposit(amount) { this.balance += amount }
    }
    const a = new Account(), b = new Account()
    a.deposit(50)
    console.log(a.balance, b.balance, Account.opened)  // 50 0 2

## ~C++~
    class Account {
    public:
        static int opened;      // shared by all accounts
        long long balance = 0;
        Account() { opened++; }
        void deposit(long long amount) { balance += amount; }
    };
    int Account::opened = 0;
    // in main:
    Account a, b;
    a.deposit(50);
    cout << a.balance << " " << b.balance << " " << Account::opened << "\n";  // 50 0 2

## الـ~complexity~
`O(1)` لكل أمر في المتوسط باستخدام ~hash map~.

## انتبه إلى
- خمسة إيداعات بقيمة `1,000,000,000` تصنع `5,000,000,000`، لذا استخدم أعدادًا صحيحة بحجم ~64-bit~.
- سحب الرصيد كاملًا بالضبط مسموح ويترك `0`.

~dictionary~ من أعداد عادية يجتاز الاختبارات أيضًا؛ فنظام التصحيح لا يرى إلا الأسطر. ومع ذلك اكتب الـ~class~.
TEXT,
    ],
    'stack-class' => [
        'title' => 'Stack class',
        'body' => <<<'TEXT'
الـ~encapsulation~ يعني أن الـ~object~ يخفي بياناته، ولا يسمح للكود الخارجي بتغييرها إلا عبر الـ~methods~ الخاصة به، وهي التي تحمي قواعده، مثل عدم تنفيذ ~pop~ على ~stack~ فارغ أبدًا.

يقرأ البرنامج الأوامر من الـ~standard input~، والـ~class~ المسمى `Stack` الذي تكتبه يؤدي العمل: يستدعي `main` لكل سطر `push` أو `pop` أو `peek` أو `size`.

يختلف الإخفاء من لغة إلى أخرى:
- ~Python~: الشرطة السفلية في البداية، كما في `_items`، تعني أنه داخلي، بالعُرف فقط.
- ~Java~ و~C++~: ~private fields~. الوصول من الخارج يفشل في الـ~compilation~.
- ~JavaScript~: ~field~ من نوع `#private`. الوصول من الخارج يسبب `SyntaxError`.

## مثال
`PUSH 3`، ثم `PUSH 7`، ثم `PEEK` يطبع `7`، و`POP` يطبع `7` ويزيله، و`SIZE` يطبع `1`. على ~stack~ فارغ، يطبع `POP` و`PEEK` الكلمة `empty`.

## طريقة التفكير في الحل
احفظ العناصر في ~list~ داخل الـ~class~، والـ~top~ في نهايته. `pop` و`peek` لا يُرجعان شيئًا (`None` أو `null`) على ~stack~ فارغ، و`main` يطبع `empty`. أما `size` فهو ~getter~: يقرأ بيانات ~private~ دون كشفها. في ~Java~ و~C++~، توضع الأسطر التي بعد `// in main:` داخل `main`.

## ~Python~
    class Stack:
        def __init__(self):
            self._items = []
        def push(self, x):
            self._items.append(x)
        def pop(self):
            return self._items.pop() if self._items else None
        def size(self):
            return len(self._items)

    s = Stack()
    s.push(4)
    print(s.pop(), s.pop(), s.size())  # 4 None 0

## ~Java~
    class Stack {
        private final ArrayList<Long> items = new ArrayList<>();
        void push(long x) { items.add(x); }
        Long pop() { return items.isEmpty() ? null : items.remove(items.size() - 1); }
        int size() { return items.size(); }
    }
    // in main:
    Stack s = new Stack();
    s.push(4);
    System.out.println(s.pop() + " " + s.pop() + " " + s.size());  // 4 null 0

## ~JavaScript~
    class Stack {
      #items = []
      push(x) { this.#items.push(x) }
      pop() { return this.#items.length > 0 ? this.#items.pop() : null }
      size() { return this.#items.length }
    }
    const s = new Stack()
    s.push(4)
    console.log(s.pop(), s.pop(), s.size())  // 4 null 0

## ~C++~
    class Stack {
        vector<long long> items;  // class members are private by default
    public:
        void push(long long x) { items.push_back(x); }
        bool empty() const { return items.empty(); }
        long long pop() { long long top = items.back(); items.pop_back(); return top; }
        int size() const { return items.size(); }
    };
    // in main:
    Stack s;
    s.push(4);
    cout << s.pop() << " " << s.empty() << " " << s.size() << "\n";  // 4 1 0

## الـ~complexity~
`O(1)` لكل أمر.

## انتبه إلى
- يجب ألا يزيل `PEEK` الـ~top~؛ أما `POP` فيجب أن يزيله.
- `0` قيمة، وليس فراغًا. `if value:` يعامل `0` كأنه لا شيء.
- في ~C++~، افحص `empty()` قبل `back()`.

~list~ عادي داخل `main` يجتاز الاختبارات أيضًا؛ فنظام التصحيح لا يستطيع رؤية ما هو ~private~.
TEXT,
    ],
    'shapes' => [
        'title' => 'الأشكال',
        'body' => <<<'TEXT'
الـ~subclass~ يرث من ~base class~، ويمكنه أن يطبّق ~override~ على الـ~methods~ الخاصة بالـ~base class~: `Square` هو `Rectangle` أضلاعه متساوية. الـ~polymorphism~ يعني أن ~loop~ يستدعي `area()` على ~list~ من `Shape` ينفّذ النسخة الخاصة بكل ~object~.

يقرأ البرنامج الأشكال من الـ~standard input~، والـ~classes~ التي تكتبها تؤدي العمل: يضع `main` لكل سطر `Circle` أو `Rectangle` أو `Square` في ~list~ واحد.

## مثال
`circle 1` و`rect 2 3` و`square 4` تطبع `3.14` و`6.00` و`16.00`، ثم `total 25.14`. قرّب عند الطباعة فقط، لا قبل الجمع.

## طريقة التفكير في الحل
يطبّق `Circle` ~override~ على `area()` بالصيغة `pi * r * r`، مستخدمًا `math.pi` أو `Math.PI` أو `M_PI`. استخدام `3.14` بدلًا منه خطأ: فهو يعطي `314.00` عندما `r = 10`، لا `314.16`. في ~Java~ و~C++~، توضع الأسطر التي بعد `// in main:` داخل `main`.

## ~Python~
    class Shape:
        def area(self):
            return 0.0
    class Rectangle(Shape):
        def __init__(self, w, h):
            self.w, self.h = w, h
        def area(self):  # overrides Shape.area
            return self.w * self.h
    class Square(Rectangle):
        def __init__(self, s):
            super().__init__(s, s)  # parent constructor

    for shape in [Rectangle(2, 3), Square(4)]:
        print(f"{shape.area():.2f}")  # 6.00, then 16.00

## ~Java~
    class Shape { double area() { return 0; } }
    class Rectangle extends Shape {
        double w, h;
        Rectangle(double w, double h) { this.w = w; this.h = h; }
        @Override double area() { return w * h; }
    }
    class Square extends Rectangle { Square(double s) { super(s, s); } }
    // in main:
    for (Shape shape : List.of(new Rectangle(2, 3), new Square(4)))
        System.out.println(String.format(Locale.ROOT, "%.2f", shape.area()));  // 6.00, 16.00

## ~JavaScript~
    class Shape { area() { return 0 } }
    class Rectangle extends Shape {
      constructor(w, h) { super(); this.w = w; this.h = h }
      area() { return this.w * this.h }
    }
    class Square extends Rectangle { constructor(s) { super(s, s) } }
    for (const shape of [new Rectangle(2, 3), new Square(4)]) console.log(shape.area().toFixed(2))  // 6.00, 16.00

## ~C++~
    class Shape {
    public:
        virtual double area() const { return 0; }
        virtual ~Shape() = default;
    };
    class Rectangle : public Shape {
        double w, h;
    public:
        Rectangle(double w, double h) : w(w), h(h) {}
        double area() const override { return w * h; }
    };
    class Square : public Rectangle { public: Square(double s) : Rectangle(s, s) {} };
    // in main:
    Rectangle rect(2, 3);
    Square square(4);
    const Shape* shapes[] = {&rect, &square};  // base-type pointers
    for (const Shape* shape : shapes) printf("%.2f\n", shape->area());  // 6.00, 16.00

## الـ~complexity~
`O(n)` لعدد `n` من الأشكال.

## انتبه إلى
- في ~C++~، بدون `virtual` ينفّذ `shape->area()` نسخة `Shape` ويطبع `0.00`.
- `String.format` في ~Java~ يتبع الـ~locale~ الخاص بالكمبيوتر، فمع الألمانية يُطبع `6,00`؛ أما `Locale.ROOT` فيطبع نقطة.

استخدام `if` على اسم الشكل يجتاز الاختبارات أيضًا؛ فنظام التصحيح لا يرى إلا الأعداد.
TEXT,
    ],
    'fee-calculator' => [
        'title' => 'حاسبة الرسوم',
        'body' => <<<'TEXT'
الـ~interface~ يسرد ~methods~ يتعهد الـ~class~ بوجودها، دون كود. أما الـ~abstract class~ فلا يمكن إنشاؤه مباشرة، وفيه ~abstract methods~ بلا ~body~، لتكتبها الـ~subclasses~. كلاهما يتيح استدعاء `fee(amount)` من `main` على أي `Fee` دون معرفة نوعه.

يقرأ البرنامج الأسطر من الـ~standard input~، والـ~classes~ التي تكتبها تؤدي العمل: يبحث `main` عن `Fee` المناسب لاسم كل طريقة دفع ويستدعيه.

تستخدم ~Python~ `ABC`، و~Java~ ~interface~، و~C++~ ~pure virtual method~ (`= 0`)؛ أما ~JavaScript~ فليس فيها شيء من ذلك، لذا يحل محله ~method~ في الـ~base class~ يرمي ~exception~.

## مثال
`card 100` و`cash 50` و`transfer 10` تطبع `2` و`0` و`5`، ثم `total 7`. و`card 49` يعطي `0.98`، ويُقرَّب إلى الأسفل إلى `0`.

## طريقة التفكير في الحل
احفظ ~object~ واحدًا لكل ~class~ في ~map~ من الاسم إلى `Fee`. رسوم البطاقة تحتاج إلى ~integer division~ (`//` في ~Python~، و`Math.floor` في ~JavaScript~)، إذ إن `round(0.98)` يعطي `1`. في ~Java~ و~C++~، توضع الأسطر التي بعد `// in main:` داخل `main`.

## ~Python~
    from abc import ABC, abstractmethod
    class Fee(ABC):
        @abstractmethod
        def fee(self, amount): ...
    class Card(Fee):
        def fee(self, amount):
            return amount * 2 // 100
    class Transfer(Fee):
        def fee(self, amount):
            return 5

    methods = {"card": Card(), "transfer": Transfer()}
    print(methods["card"].fee(149), methods["transfer"].fee(149))  # 2 5

## ~Java~
    interface Fee { long fee(long amount); }
    class Card implements Fee { public long fee(long amount) { return amount * 2 / 100; } }
    class Transfer implements Fee { public long fee(long amount) { return 5; } }
    // in main:
    Map<String, Fee> methods = Map.of("card", new Card(), "transfer", new Transfer());
    System.out.println(methods.get("card").fee(149) + " " + methods.get("transfer").fee(149));  // 2 5

## ~JavaScript~
    class Fee { fee(amount) { throw new Error('not implemented') } }
    class Card extends Fee { fee(amount) { return Math.floor(amount * 2 / 100) } }
    class Transfer extends Fee { fee(amount) { return 5 } }
    const methods = { card: new Card(), transfer: new Transfer() }
    console.log(methods.card.fee(149), methods.transfer.fee(149))  // 2 5

## ~C++~
    class Fee {
    public:
        virtual long long fee(long long amount) const = 0;
    };
    class Card : public Fee {
    public: long long fee(long long amount) const override { return amount * 2 / 100; }
    };
    class Transfer : public Fee {
    public: long long fee(long long) const override { return 5; }
    };
    // in main:
    Card card;
    const Fee& f = card;
    cout << f.fee(149) << " " << Transfer().fee(149) << "\n";  // 2 5

## الـ~complexity~
`O(n)` لعدد `n` من الأسطر.

## انتبه إلى
- في ~Python~، يرمي `Fee()` الخطأ `TypeError`؛ وفي ~C++~، يفشل `Fee f;` في الـ~compilation~.
- في ~Java~، يجب أن يكون الـ~method~ الذي يطبّق ~interface~ من نوع `public`.

استخدام `if` على اسم طريقة الدفع يجتاز الاختبارات أيضًا؛ فنظام التصحيح لا يرى إلا الأعداد.
TEXT,
    ],
    'linear-search' => [
        'title' => 'Linear search',
        'body' => <<<'TEXT'
الـ~linear search~ ينظر في العناصر واحدًا تلو الآخر، من البداية، حتى يجد الـ~target~ أو تنتهي العناصر. يعمل على أي ~list~، مرتبًا كان أم لا.

## مثال
ابحث عن `8` في `3 8 -2 8 5`.
الـ~index~ `0`: `3` ليس `8`.
الـ~index~ `1`: `8` هو الـ~target~. توقف: الإجابة `1`.
أما `8` الثاني، عند الـ~index~ `3`، فلا يُفحص أبدًا.
ابحث عن `4` في الـ~list~ نفسه: تُفحص العناصر كلها وعددها `5`، ولا يطابق أي منها، لذا الإجابة `-1`.

## طريقة التفكير في الحل
مُرّ على الـ~indexes~ من `0` إلى `n - 1`، وأرجِع أول ~index~ يساوي عنصره الـ~target~. لا تُرجع `-1` إلا بعد انتهاء الـ~loop~: عندها فقط يُعرف أن الـ~target~ غير موجود.

## ~Python~
    def find(a, target):
        for i in range(len(a)):
            if a[i] == target:
                return i
        return -1

## ~Java~
    static int find(int[] a, int target) {
        for (int i = 0; i < a.length; i++)
            if (a[i] == target) return i;
        return -1;
    }

## ~JavaScript~
    function find(a, target) {
      for (let i = 0; i < a.length; i++)
        if (a[i] === target) return i
      return -1
    }

## ~C++~
    int find(const vector<int>& a, int target) {
        for (int i = 0; i < (int)a.size(); i++)
            if (a[i] == target) return i;
        return -1;
    }

## الـ~complexity~
الـ~time complexity~: `O(n)`. الـ~target~ غير الموجود يكلف `n` مقارنة، واحدة لكل عنصر، والـ~target~ الموجود عند الـ~index~ `i` يكلف `i + 1`. ضعف عدد العناصر يعني ضعف العمل.
الـ~space complexity~: `O(1)`: الـ~index~ `i` فقط.

## انتبه إلى
- آخر تطابق بدلًا من الأول. الـ~loop~ الذي يحفظ `i` عند كل تطابق ويستمر ينتهي بآخر تطابق: `3` في المثال، لا `1`. أرجِع النتيجة عند أول تطابق.
- إرجاع `0` عندما يكون الـ~target~ غير موجود. `0` ~index~ حقيقي، لذا يجب أن تكون نتيجة "غير موجود" هي `-1`.
- إرجاع `-1` داخل الـ~loop~ عند أول عدم تطابق. عندها لا يُفحص إلا الـ~index~ `0`.

الـ~binary search~، الذي يأتي لاحقًا في الدورة، أسرع لكنه يحتاج إلى ~sorted list~.
TEXT,
    ],
    'greatest-common-divisor' => [
        'title' => 'القاسم المشترك الأكبر',
        'body' => <<<'TEXT'
القاسم المشترك الأكبر `gcd(a, b)` هو أكبر عدد يقسم كلًا من `a` و`b`. كل عدد يقسم `0`، لذا `gcd(a, 0) = a`.

يقوم ~Euclid's algorithm~ على حقيقة واحدة: العدد يقسم `a` و`b` إذا وفقط إذا كان يقسم `b` و`a % b`. لذا `gcd(a, b) = gcd(b, a % b)`. كرر ذلك حتى يصبح `b` صفرًا؛ عندها تكون `a` هي الإجابة.

## مثال
`gcd(48, 18)`:
`48 % 18 = 12`، إذن `gcd(18, 12)`.
`18 % 12 = 6`، إذن `gcd(12, 6)`.
`12 % 6 = 0`، إذن `gcd(6, 0) = 6`.
`gcd(18, 48)` يحتاج إلى خطوة إضافية واحدة: `18 % 48 = 18`، وهي تبدّل العددين فقط.

## طريقة التفكير في الحل
طرح العدد الأصغر من الأكبر حتى يتساويا يعمل أيضًا؛ و`%` تنفّذ كل عمليات الطرح هذه دفعة واحدة. مع `10^18` و`10^18 - 1`، يحتاج ~Euclid's algorithm~ إلى خطوتين، ويحتاج الطرح إلى `999999999999999999` خطوة: نحو `32` سنة بمعدل مليار خطوة في الثانية.

## ~Python~
    def gcd(a, b):
        while b != 0:
            a, b = b, a % b
        return a

## ~Java~
    static long gcd(long a, long b) {
        while (b != 0) {
            long t = a % b;
            a = b;
            b = t;
        }
        return a;
    }

## ~JavaScript~
    function gcd(a, b) {  // a and b are BigInts
      while (b !== 0n) [a, b] = [b, a % b]
      return a
    }

## ~C++~
    long long gcd(long long a, long long b) {
        while (b != 0) {
            long long t = a % b;
            a = b;
            b = t;
        }
        return a;
    }

## الـ~complexity~
الـ~time complexity~: `O(log n)` خطوة للأعداد حتى `n`. حتى `10^18`، لا يحتاج ~Euclid's algorithm~ أبدًا إلى أكثر من `86` خطوة.
الـ~space complexity~: `O(1)`.

## انتبه إلى
- `Number` في ~JavaScript~ دقيق فقط حتى `2^53 - 1 = 9007199254740991`. القيمة `Number("999999999999999999")` تساوي `10^18`، لذا يعطي `gcd(10^18, 10^18 - 1)` النتيجة `10^18` لا `1`. استخدم `BigInt(text)`، واطبع `String(g)`: لأن `console.log(g)` يضيف `n`.
- يحدث ~overflow~ في `int`: استخدم `long` في ~Java~، و`long long` في ~C++~.
- `a = 0` لا تحتاج إلى معالجة خاصة.
TEXT,
    ],
    'prime-check' => [
        'title' => 'فحص الأعداد الأولية',
        'body' => <<<'TEXT'
العدد الأولي عدد صحيح أكبر من `1` ليس له قواسم إلا `1` ونفسه. العددان `0` و`1` ليسا أوليين: للعدد `1` قاسم واحد فقط، وكل عدد يقسم `0`.

## مثال
هل `91` أولي؟ `sqrt(91)` تقريبًا `9.5`، لذا جرّب من `2` إلى `9`.
`2` و`3` و`4` و`5` و`6` لا تقسم `91`. أما `7` فيقسمه: `91 = 7 * 13`. ليس أوليًا.
هل `97` أولي؟ `sqrt(97)` تقريبًا `9.8`، ولا يقسم `97` أي عدد من `2` إلى `9`. أولي.

## طريقة التفكير في الحل
إذا كان `n = a * b`، فإن الأصغر من `a` و`b` لا يتجاوز `sqrt(n)`. لذا إذا لم يقسم `n` أي عدد من `2` إلى `sqrt(n)`، فلن يقسمه أي عدد أكبر. اكتب الحد بالشكل `i * i <= n`.

## ~Python~
    def is_prime(n):
        if n < 2:
            return False
        i = 2
        while i * i <= n:
            if n % i == 0:
                return False
            i += 1
        return True

## ~Java~
    static boolean isPrime(long n) {
        if (n < 2) return false;
        for (long i = 2; i * i <= n; i++)
            if (n % i == 0) return false;
        return true;
    }

## ~JavaScript~
    function isPrime(n) {
      if (n < 2) return false
      for (let i = 2; i * i <= n; i++)
        if (n % i === 0) return false
      return true
    }

## ~C++~
    bool isPrime(long long n) {
        if (n < 2) return false;
        for (long long i = 2; i * i <= n; i++)
            if (n % i == 0) return false;
        return true;
    }

## الـ~complexity~
الـ~time complexity~: `O(sqrt(n))`. العدد الأولي القريب من `10^12` يحتاج إلى نحو `10^6` عملية قسمة؛ أما ~loop~ حتى `n / 2` فيحتاج إلى `5 * 10^11` ويتجاوز الوقت المسموح.
الـ~space complexity~: `O(1)`.

## انتبه إلى
- `int i` في ~Java~ أو ~C++~. يحدث ~overflow~ في `i * i` عند `i = 46341`، فيستمر الـ~loop~ بعد `sqrt(n)`، ويتجاوز العدد الأولي الكبير الوقت المسموح. استخدم `long` (`long long` في ~C++~).
- حذف الشرط `n < 2` يجعل `0` أو `1` أوليًا.
- `i * i < n` بدلًا من `<=` يجعل `49` أوليًا.
TEXT,
    ],
    'count-primes-with-a-sieve' => [
        'title' => 'عدّ الأعداد الأولية باستخدام الـ sieve',
        'body' => <<<'TEXT'
يجد ~Sieve of Eratosthenes~ كل الأعداد الأولية حتى `n` دفعة واحدة. أصغر عدد لم يُشطب بعد هو عدد أولي: اشطب مضاعفاته، وكرر.

## مثال
`n = 30`.
`2`: اشطب `4, 6, ..., 30`.
`3`: اشطب `9, 12, ..., 30`.
`5`: اشطب `25, 30`.
التالي هو `7`، و`7 * 7 = 49` أكبر من `30`: توقف.
الباقي: `2 3 5 7 11 13 17 19 23 29`، أي `10` أعداد أولية.

## طريقة التفكير في الحل
ابدأ من `p * p`: فالمضاعفات الأصغر، مثل `2 * p`، شُطبت من قبل. توقف عندما `p * p > n`، ثم عدّ ما بقي من `2` إلى `n`. استخدم `n + 1` خانة حتى يكون `n` نفسه مشمولًا.

## ~Python~
    composite = [False] * (n + 1)
    p = 2
    while p * p <= n:
        if not composite[p]:
            for m in range(p * p, n + 1, p):
                composite[m] = True
        p += 1

## ~Java~
    boolean[] composite = new boolean[n + 1];
    for (int p = 2; p * p <= n; p++)
        if (!composite[p])
            for (int m = p * p; m <= n; m += p) composite[m] = true;

## ~JavaScript~
    const composite = new Uint8Array(n + 1)
    for (let p = 2; p * p <= n; p++)
      if (!composite[p])
        for (let m = p * p; m <= n; m += p) composite[m] = 1

## ~C++~
    vector<bool> composite(n + 1, false);
    for (int p = 2; p * p <= n; p++)
        if (!composite[p])
            for (int m = p * p; m <= n; m += p) composite[m] = true;

## الـ~complexity~
الـ~time complexity~: `O(n log log n)`، بضع خطوات لكل عدد: `11192870` عملية شطب عندما `n = 5000000`. أما ~trial division~ لكل عدد فيحتاج إلى `654184150` عملية قسمة، أي `58` ضعفًا.
الـ~space complexity~: `O(n)`.

## انتبه إلى
- خطأ ~off-by-one~: استخدام `n` خانة، أو عدّ يتوقف عند `n - 1`، يُسقط `n`. فيعطي `n = 3` النتيجة `1` لا `2`.
- ~trial division~ لكل عدد: صحيح، لكنه في ~Python~ يتجاوز الحد الزمني البالغ `3` ثوانٍ.
TEXT,
    ],
    'reverse-a-string' => [
        'title' => 'عكس الـ string',
        'body' => <<<'TEXT'
الـ~string~ سلسلة من الـ~characters~، لذا يمكنك التعامل معه مثل ~array~. عكسه يضع آخر ~character~ أولًا، وأول ~character~ آخرًا.

الـ~two-pointer technique~ يؤدي ذلك ببساطة. ضع ~pointer~ في البداية وآخر في النهاية، وبدّل الـ~characters~ اللذين يشيران إليهما، وحرّك الـ~pointers~ الاثنين خطوة نحو المنتصف. توقف عندما يلتقيان أو يتقاطعان.

## مثال
خذ `"abc def"`، وفيه `7` ~characters~ في المواضع من `0` إلى `6`.
بدّل الموضعين `0` و`6`: `"fbc dea"`.
بدّل الموضعين `1` و`5`: `"fec dba"`.
بدّل الموضعين `2` و`4`: `"fed cba"`.
يصل الـ~pointers~ الاثنان إلى الموضع `3`، وهو المسافة، لذا توقف. النتيجة `"fed cba"`.

## طريقة التفكير في الحل
في ~Python~ و~Java~ و~JavaScript~، لا يمكن تغيير الـ~strings~ ~in place~. انسخ الـ~characters~ إلى ~list~ أو ~array~، وبدّل هناك، ثم اجمعها في ~string~ من جديد. وهناك طريقة أخرى: بناء ~string~ جديد بقراءة الأصلي من آخر ~character~ إلى أوله. الطريقتان تعطيان الإجابة نفسها؛ ونسخة الـ~two pointers~ تستحق أن تُكتب مرة، لأن الدروس التالية تعيد استخدامها.

## الـ~complexity~
الـ~time complexity~: `O(n)`، لأن كل ~character~ يُنقل مرة واحدة.
الـ~space complexity~: `O(n)` من أجل الـ~character array~ أو الـ~string~ الجديد. التبديل داخل ~string~ قابل للتغيير، مثل `std::string` في ~C++~، لا يحتاج إلا إلى `O(1)` إضافية.

## انتبه إلى
- المسافات جزء من النص. اقرأ السطر كله، لا كلمات منفردة: في ~Java~ استخدم `nextLine()` لا `next()`؛ وفي ~C++~ استخدم `getline` لا `cin >> s`. تُعكس المسافات مثل أي ~character~ آخر.
- السطر الفارغ. الإجابة سطر فارغ. تأكد أن كود القراءة يقبله بدلًا من أن ينهار أو ينتظر مزيدًا من الـ~input~.
- الأطوال الفردية والزوجية. عندما يكون `left < right` هو الـ~loop condition~، تعمل الحالتان، ويبقى الـ~character~ الأوسط في الـ~string~ ذي الطول الفردي في مكانه.

استخدم المسألة أدناه للتدرب على الـ~two pointers~ قبل درس الـ~palindrome~، الذي يستخدم الفكرة نفسها.
TEXT,
    ],
    'two-sum' => [
        'title' => 'Two Sum',
        'body' => <<<'TEXT'
تطلب مسألة ~Two Sum~ موضعين مجموع قيمتيهما يساوي الـ~target~. الطريقة المباشرة تفحص كل زوج. أما الطريقة الأسرع فتتذكر القيم التي ظهرت من قبل، فلا يحتاج كل عدد إلا إلى ~lookup~ واحد: هل ظهر شريكه سابقًا؟

## مثال
خذ `2 7 11 15` مع الـ~target~ `9`.
الموضع `0`، القيمة `2`: الشريك سيكون `9 - 2 = 7`. لم يظهر شيء بعد، لذا احفظ `2` عند الموضع `0`.
الموضع `1`، القيمة `7`: الشريك سيكون `9 - 7 = 2`. ظهر `2` عند الموضع `0`، لذا الإجابة `0 1`.

## طريقة التفكير في الحل
مُرّ على الـ~array~ مرة واحدة مع ~hash map~ من القيمة إلى الموضع: `dict` في ~Python~، و`HashMap` في ~Java~، و`Map` في ~JavaScript~، و`unordered_map` في ~C++~. لكل قيمة، احسب `target - value` وابحث عنه. إذا وُجد، فقد وجدت الزوج، ويأتي الموضع المخزن أولًا لأنه الأسبق. وإن لم يوجد، فخزّن القيمة الحالية وموضعها وتابع. ابحث قبل أن تخزّن، حتى لا يقترن عدد بنفسه أبدًا.

الـ~nested loops~ التي تجرب كل زوج تعمل أيضًا. مع `n` حتى `2000` يكون ذلك نحو `2` مليون زوج، وهذا يجتاز الاختبارات هنا.

## الـ~complexity~
الـ~time complexity~: `O(n)` في المتوسط، لأن كل قيمة تحتاج إلى ~lookup~ واحد و~insert~ واحد، وكلاهما يستغرق `O(1)` في المتوسط في الـ~hash map~. أما الـ~nested loops~ فتستغرق `O(n^2)`.
الـ~space complexity~: `O(n)` من أجل الـ~map~. أما الـ~nested loops~ فتحتاج إلى `O(1)`.

## انتبه إلى
- القيم المتساوية. مع `3 3` والـ~target~ `6` الإجابة `0 1`. إذا خُزّنت القيمة قبل البحث عن شريكها، يجد أول `3` نفسه ويُطبع `0 0`.
- الأعداد السالبة. مع `4 -3 9 1 -5` والـ~target~ `-8`، الزوج هو `-3` و`-5`، عند الموضعين `1` و`4`.
- الـ~sorting~. إجراء ~sorting~ على الـ~array~ أولًا يُضيع المواضع الأصلية، وهي ما يجب طباعته.

اكتب نسخة الـ~hash map~ للمسألة أدناه، واستخدم الـ~nested loops~ للتحقق منها على ~inputs~ صغيرة.
TEXT,
    ],
    'check-for-a-palindrome' => [
        'title' => 'التحقق من الـ palindrome',
        'body' => <<<'TEXT'
الـ~palindrome~ يُقرأ بالطريقة نفسها في الاتجاهين: `"level"` و`"racecar"` و`"abba"`. التحقق منه يستخدم الـ~two pointers~ من درس عكس الـ~string~، لكن بدلًا من تبديل الـ~characters~ تُقارن.

## مثال
`"abba"`: قارن الموضعين `0` و`3`، `a` و`a`، متساويان. قارن الموضعين `1` و`2`، `b` و`b`، متساويان. تقاطع الـ~pointers~ الآن وتطابق كل زوج، لذا اطبع `yes`.
`"hello"`: قارن الموضعين `0` و`4`، `h` و`o`. إنهما مختلفان، لذا توقف فورًا واطبع `no`.

## طريقة التفكير في الحل
ابدأ بوضع `left` عند أول ~character~ و`right` عند آخر ~character~. ما دام `left < right`، قارن الـ~characters~ الاثنين وحرّك الـ~pointers~ الاثنين نحو الداخل. عند أول عدم تطابق تكون الإجابة `no`. وإذا انتهى الـ~loop~ تكون الإجابة `yes`. لا يُفحص أي ~character~ مرتين، وعدم التطابق قرب الطرفين يُنهي العمل مبكرًا.

مقارنة الكلمة بنسخة معكوسة منها تعمل أيضًا وكتابتها أقصر. لكنها تكلف ذاكرة للنسخة، وتقرأ الكلمة كلها دائمًا.

## الـ~complexity~
الـ~time complexity~: `O(n)`، لأن عدد الأزواج المقارنة لا يتجاوز `n/2`.
الـ~space complexity~: `O(1)` إضافية مع الـ~two pointers~؛ و`O(n)` عند بناء نسخة معكوسة.

## انتبه إلى
- ~character~ واحد. `"a"` هو ~palindrome~. مع `left < right` لا يعمل الـ~loop~ أبدًا، والإجابة `yes`.
- الأطوال الزوجية والفردية. `"abba"` ليس فيه ~character~ أوسط، و`"racecar"` فيه واحد. الـ~loop condition~ نفسه يعالج الحالتين، لأن الـ~character~ الأوسط لا يحتاج إلا أن يساوي نفسه.
- ~newline~ زائد. في ~JavaScript~، يكون في نهاية الـ~input~ المقروء باستخدام `readFileSync` ~newline~. احذفه، وإلا يكون آخر ~character~ هو `"\n"` ولن تبدو أي كلمة ~palindrome~ أبدًا.
- الـ~output~ بالضبط. اطبع `yes` أو `no` بأحرف صغيرة، دون علامات اقتباس أو كلمات إضافية.

المسألة أدناه تطبيق مباشر لهذا الـ~loop~؛ اختبره على كلمة طويلة إلى جانب الكلمات القصيرة.
TEXT,
    ],
    'balanced-brackets' => [
        'title' => 'الأقواس المتوازنة',
        'body' => <<<'TEXT'
الـ~stack~ مجموعة تعمل بمبدأ ~last-in, first-out~: تضيف العناصر إلى الـ~top~ بعملية ~push~، وتأخذها من الـ~top~ بعملية ~pop~. وهذا يناسب الأقواس تمامًا، لأن آخر قوس فُتح يجب أن يكون أول قوس يُغلق.

## مثال
`"([]{})"`:
`(` قوس فتح: ~push~. الـ~stack~: `(`
`[` قوس فتح: ~push~. الـ~stack~: `( [`
`]` يغلق `[`، وهو في الـ~top~: ~pop~. الـ~stack~: `(`
`{` قوس فتح: ~push~. الـ~stack~: `( {`
`}` يغلق `{`، وهو في الـ~top~: ~pop~. الـ~stack~: `(`
`)` يغلق `(`، وهو في الـ~top~: ~pop~. الـ~stack~ فارغ.
انتهى السطر والـ~stack~ فارغ، لذا الإجابة `yes`.
في `"([)]"`، يجد الحرف الثالث `)` القوس `[` في الـ~top~. هذا ليس النوع المطابق، لذا الإجابة `no`.

## طريقة التفكير في الحل
نفّذ ~push~ لكل قوس فتح. ومع كل قوس إغلاق، يجب ألا يكون الـ~stack~ فارغًا، ويجب أن يكون الـ~top~ هو قوس الفتح المطابق؛ عندها نفّذ ~pop~ وتابع. وإلا فتوقف والإجابة `no`. عندما ينتهي السطر، تكون الإجابة `yes` فقط إذا كان الـ~stack~ فارغًا. ~map~ صغير من كل قوس إغلاق إلى قوس الفتح الخاص به يجعل الفحص قصيرًا. في ~Python~ يمكن استخدام الـ~list~ على أنه ~stack~ (`append` و`pop`)؛ وفي ~Java~ استخدم `ArrayDeque`، وفي ~JavaScript~ ~array~، وفي ~C++~ `std::stack` أو `std::vector`.

## الـ~complexity~
الـ~time complexity~: `O(n)`، لأن كل حرف يُنفَّذ عليه ~push~ و~pop~ مرة واحدة على الأكثر.
الـ~space complexity~: `O(n)` في أسوأ حالة، عندما يكون كل حرف قوس فتح.

## انتبه إلى
- السطر الفارغ متوازن، لذا الإجابة `yes`.
- أقواس فتح متبقية. في `"("` لا يحدث أي خطأ أثناء الـ~loop~، لذا يجب التحقق في النهاية من أن الـ~stack~ فارغ.
- قوس إغلاق مع ~stack~ فارغ. في `")("` لا يجد الحرف الأول ما يطابقه. تحقق من أن الـ~stack~ غير فارغ قبل ~pop~: تنفيذ ~pop~ على ~list~ فارغ في ~Python~ أو على `ArrayDeque` فارغ في ~Java~ يرمي ~exception~.
- العدّ لا يكفي. في `"([)]"` يقابل كل قوس إغلاق قوس فتح، ومع ذلك فهو غير صحيح.

حلّ المسألة أدناه باستخدام ~stack~ بدلًا من ~counters~.
TEXT,
    ],
    'evaluate-a-postfix-expression' => [
        'title' => 'حساب قيمة الـ postfix expression',
        'body' => <<<'TEXT'
في الـ~postfix notation~، وتسمى أيضًا ~reverse Polish notation~، يأتي كل ~operator~ بعد الـ~operands~ الاثنين الخاصين به. `"3 4 +"` تعني `3 + 4`، و`"3 4 + 2 *"` تعني `(3 + 4) * 2`. ترتيب الـ~tokens~ يحدد وحده ترتيب العمليات، لذا لا حاجة إلى الأقواس، ويحسب الـ~stack~ قيمة التعبير في مرور واحد.

## مثال
`"3 4 + 2 *"`:
`3` عدد: ~push~. الـ~stack~: `3`
`4` عدد: ~push~. الـ~stack~: `3 4`
`+` يأخذ `4` بعملية ~pop~، ثم `3`، وينفّذ ~push~ للقيمة `3 + 4`. الـ~stack~: `7`
`2` عدد: ~push~. الـ~stack~: `7 2`
`*` يأخذ `2` بعملية ~pop~، ثم `7`، وينفّذ ~push~ للقيمة `7 * 2`. الـ~stack~: `14`
لم يبقَ أي ~token~. القيمة الوحيدة في الـ~stack~، `14`، هي الإجابة.

## طريقة التفكير في الحل
قسّم السطر عند المسافات. نفّذ ~push~ لكل عدد. ومع كل ~operator~، نفّذ ~pop~ مرتين: الأولى تعطي الـ~operand~ الأيمن، والثانية تعطي الأيسر. احسب `left operator right` ونفّذ ~push~ للنتيجة. التعبير الصحيح يترك في النهاية قيمة واحدة بالضبط في الـ~stack~.

## الـ~complexity~
الـ~time complexity~: `O(n)` مع `n` من الـ~tokens~، لأن كل ~token~ يُعالَج مرة واحدة، وكل ~push~ أو ~pop~ يستغرق `O(1)`.
الـ~space complexity~: `O(n)` من أجل الـ~stack~ في أسوأ حالة، عندما تأتي أعداد كثيرة قبل أول ~operator~.

## انتبه إلى
- ترتيب الـ~operands~. `"2 7 -"` تساوي `2 - 7 = -5`، لا `7 - 2`. وفي القسمة الفخ نفسه.
- القسمة تقرّب نحو الصفر. `"0 7 - 2 /"` تساوي `-7 / 2 = -3`. الـ~integer division~ في ~Java~ و~C++~ تقرّب نحو الصفر أصلًا. في ~Python~، يقرّب `//` إلى الأسفل ويعطي `-4`، لذا استخدم `int(a / b)`. وفي ~JavaScript~، استخدم `Math.trunc(a / b)`.
- عدد واحد. `"42"` لا يحتوي على أي ~operator~، والإجابة `42`.
- النتائج السالبة. أعداد الـ~input~ لا تكون سالبة أبدًا، لكن النتائج الوسيطة قد تكون سالبة، كما في `"2 7 -"`.

اكتب الـ~loop~ الذي يستخدم الـ~stack~ للمسألة أدناه، واختبر حالة ترتيب الـ~operands~ أولًا.
TEXT,
    ],
    'next-greater-element' => [
        'title' => 'Next greater element',
        'body' => <<<'TEXT'
لكل عدد، الـ~next greater element~ هو أول عدد على يمينه أكبر منه تمامًا، أو `-1` إذا لم يوجد. المسح نحو اليمين من كل موضع يعمل، لكنه يكرر الكثير من العمل. ~stack~ من المواضع التي ما زالت تنتظر إجابتها يزيل هذا التكرار.

## مثال
خذ `4 5 2 25`.
الموضع `0`، القيمة `4`: الـ~stack~ فارغ. نفّذ ~push~ للموضع `0`.
الموضع `1`، القيمة `5`: `5 > 4`، لذا إجابة الموضع `0` هي `5`. نفّذ ~pop~ له، ثم ~push~ للموضع `1`.
الموضع `2`، القيمة `2`: `2` ليس أكبر من `5`. نفّذ ~push~ للموضع `2`.
الموضع `3`، القيمة `25`: `25 > 2`، لذا يأخذ الموضع `2` الإجابة `25`. و`25 > 5`، لذا يأخذ الموضع `1` الإجابة `25`. نفّذ ~push~ للموضع `3`.
النهاية: الموضع `3` ما زال ينتظر، لذا تبقى إجابته `-1`.
اطبع `5 25 25 -1`.

## طريقة التفكير في الحل
املأ ~array~ الإجابات بالقيمة `-1`. امشِ من اليسار إلى اليمين. ما دام الـ~stack~ غير فارغ والقيمة الحالية أكبر من القيمة عند الموضع الموجود في الـ~top~، فقد وجد ذلك الموضع إجابته: اكتبها ونفّذ ~pop~. ثم نفّذ ~push~ للموضع الحالي. القيم عند المواضع المخزنة في الـ~stack~ لا تزداد أبدًا من الأسفل إلى الـ~top~، لذا يكون الـ~top~ دائمًا هو الموضع التالي الذي سيحصل على إجابته.

الـ~brute force~، أي المسح نحو اليمين من كل موضع، يستغرق `O(n^2)`. ومع `n` حتى `2000` يجتاز الاختبارات هنا أيضًا.

## الـ~complexity~
الـ~time complexity~: `O(n)`، لأن كل موضع يُنفَّذ عليه ~push~ مرة واحدة و~pop~ مرة واحدة على الأكثر.
الـ~space complexity~: `O(n)` من أجل الـ~stack~ و~array~ الإجابات.

## انتبه إلى
- القيم المتساوية. "أكبر تمامًا" تعني أن `2` لا يُعد إجابة عن `2` يسبقه. مع `2 2 3 3 1` تكون الإجابة `3 3 -1 -1 -1`. قارن باستخدام `>`، لا `>=`.
- خزّن المواضع، لا القيم. تحتاج إلى الموضع لتعرف أين تكتب كل إجابة.
- ~array~ تنازلي. مع `-1 -2 -3 -4` لا يُنفَّذ ~pop~ أبدًا، وكل إجابة تساوي `-1`.

اكتب نسخة الـ~stack~ للمسألة أدناه، وقارنها بالـ~brute force~ على ~inputs~ صغيرة.
TEXT,
    ],
    'reverse-a-linked-list' => [
        'title' => 'عكس الـ linked list',
        'body' => <<<'TEXT'
الـ~linked list~ يخزن قيمه في ~nodes~. كل ~node~ يحمل قيمة و`next`، وهو ~pointer~ إلى الـ~node~ التالي. و`next` في آخر ~node~ لا يشير إلى شيء: `None` في ~Python~، و`null` في ~Java~ و~JavaScript~، و`nullptr` في ~C++~. الـ~list~ نفسه ~pointer~ إلى أول ~node~ فيه، أي الـ~head~. لزيارة كل قيمة، ابدأ من الـ~head~ واتبع `next` حتى تصل إلى لا شيء.

على عكس الـ~array~، لا يوجد في الـ~linked list~ ~index~ يمكن القفز إليه، لكن إعادة ترتيبه لا تتطلب إلا تغيير ~pointers~ `next`.

## مثال
خذ `1 -> 2 -> 3`، مع `prev` عند لا شيء و`cur` عند الـ~head~.
`cur = 1`: احفظ `next` الخاص به، أي `2`. اجعل `1` يشير إلى `prev`، أي لا شيء. ثم `prev = 1` و`cur = 2`.
`cur = 2`: احفظ `3`. اجعل `2` يشير إلى `1`. ثم `prev = 2` و`cur = 3`.
`cur = 3`: احفظ لا شيء. اجعل `3` يشير إلى `2`. ثم `prev = 3`، ويصبح `cur` لا شيء.
`cur` لا شيء، لذا توقف. `prev` هو الـ~head~ الجديد: `3 -> 2 -> 1`.

## طريقة التفكير في الحل
ابنِ الـ~list~ أولًا. الـ~node~ هو ~class~ صغير فيه `val` و`next` في ~Python~ و~Java~، و~object~ `{ val, next }` في ~JavaScript~، و`struct` في ~C++~. استخدم ~pointer~ إلى الـ~tail~، حتى يُضاف كل ~node~ جديد في `O(1)`.

ثم امشِ مرة واحدة باستخدام `prev` و`cur`. عند كل ~node~، احفظ `cur.next`، واجعل `cur.next` يشير إلى الخلف نحو `prev`، ثم حرّك الـ~pointers~ الاثنين خطوة إلى الأمام. عندما ينتهي `cur`، يكون `prev` هو الـ~head~ الجديد.

## الـ~complexity~
الـ~time complexity~: `O(n)`، لأن كل ~node~ يُزار مرة للبناء، ومرة للعكس، ومرة للطباعة.
الـ~space complexity~: `O(n)` من أجل الـ~nodes~؛ أما العكس نفسه فيحتاج إلى `O(1)`.

## انتبه إلى
- ضياع بقية الـ~list~. إذا نُفّذ `cur.next = prev` قبل حفظ `cur.next` القديم، تضيع الـ~nodes~ المتبقية.
- إرجاع ~head~ خطأ. بعد الـ~loop~، الـ~head~ الجديد هو `prev`، لا `cur`.
- `n = 0`. لا يعمل الـ~loop~ أبدًا، والإجابة سطر فارغ.

طباعة الـ~input~ بالعكس تجتاز المسألة أدناه أيضًا، لذا اعكس الـ~pointers~ عن قصد.
TEXT,
    ],
    'middle-of-a-linked-list' => [
        'title' => 'منتصف الـ linked list',
        'body' => <<<'TEXT'
كل ~node~ يحمل قيمة و`next`، وهو الـ~pointer~ إلى الـ~node~ التالي؛ و`next` في آخر ~node~ لا يشير إلى شيء. الـ~linked list~ لا يعرف طوله، لذا لا يمكن القفز إلى المنتصف مباشرة. العدّ يحتاج إلى ~two passes~: مرور أول لعدّ `n`، ثم التقدم `n / 2` خطوة مع التقريب إلى الأسفل. أما الـ~two pointers~ فتحتاج إلى مرور واحد: يتحرك `slow` ~node~ واحدًا في كل مرة، ويتحرك `fast` اثنين. عندما يصل `fast` إلى النهاية، يكون `slow` قد قطع نصف المسافة.

## مثال
خذ `1 2 3 4 5`. يبدأ الـ~pointers~ الاثنان من الـ~head~، أي `1`.
الخطوة 1: ينتقل `slow` إلى `2`، وينتقل `fast` إلى `3`.
الخطوة 2: ينتقل `slow` إلى `3`، وينتقل `fast` إلى `5`.
`fast.next` لا شيء، لذا توقف. `slow` عند `3`، أي المنتصف.
خذ `1 2 3 4 5 6`.
الخطوة 1: `slow` عند `2`، و`fast` عند `3`. الخطوة 2: `slow` عند `3`، و`fast` عند `5`.
الخطوة 3: ينتقل `slow` إلى `4`، ويتجاوز `fast` العدد `6` إلى لا شيء.
`fast` لا شيء، لذا توقف. `slow` عند `4`، وهو الثاني من المنتصفين `3` و`4`.

## طريقة التفكير في الحل
كرر الـ~loop~ ما دام كل من `fast` و`fast.next` يشير إلى ~node~، وحرّك `slow` خطوة واحدة و`fast` خطوتين. الفحص الأول ينهي الـ~loop~ عندما يكون `n` زوجيًا، والثاني عندما يكون `n` فرديًا. بعد ذلك يقع `slow` على المنتصف الثاني عندما يكون `n` زوجيًا، كما تطلب المسألة.

## الـ~complexity~
الـ~time complexity~: `O(n)`، لأن `fast` يعبر الـ~list~ مرة واحدة.
الـ~space complexity~: `O(1)` إضافية من أجل الـ~pointers~ الاثنين، بالإضافة إلى `O(n)` من أجل الـ~nodes~.

## انتبه إلى
- الـ~loop condition~. فحص `fast` وحده يجعل البرنامج يتعطل عندما يكون الطول فرديًا، عند قراءة `next.next` لآخر ~node~. وفحص `fast.next` وحده يجعله يتعطل عندما يكون الطول زوجيًا، بمجرد أن يصبح `fast` لا شيء.
- أي منتصف. التوقف عندما يكون `fast.next.next` لا شيء يعطي المنتصف الأول: `3` بدلًا من `4` مع `1 2 3 4 5 6`، و`10` بدلًا من `20` مع `10 20`.
- `n = 1`. لا يعمل الـ~loop~ أبدًا، والـ~head~ هو الإجابة.

يعود الـ~fast and slow pointers~ في الدرس التالي، لذا اكتبهما هنا بدلًا من العدّ أولًا.
TEXT,
    ],
    'detect-a-cycle' => [
        'title' => 'اكتشاف الـ cycle',
        'body' => <<<'TEXT'
الـ~list~ العادي ينتهي بلا شيء. إذا أشار آخر ~node~ إلى ~node~ سابق، فلن ينتهي المرور أبدًا: هذه الحلقة هي ~cycle~.

~set~ من الـ~nodes~ التي تمت زيارتها يعمل، لكنه يحتاج إلى ذاكرة إضافية `O(n)`. أما طريقة ~Floyd~ فتستخدم بدلًا منه `slow` و`fast` من درس منتصف الـ~linked list~. بلا ~cycle~، يصل `fast` إلى النهاية. ومع وجوده، ينتهي الاثنان داخل الحلقة، ويقلّص `fast` المسافة بينهما ~node~ واحدًا في كل خطوة، حتى يقع على `slow`.

## مثال
خذ `3 2 0 -4` مع `pos` يساوي `1`: الـ~node~ الذي فيه `-4` يعود ليرتبط بالـ~node~ الذي فيه `2`.
يبدأ الاثنان من `3`.
الخطوة 1: ينتقل `slow` إلى `2`، وينتقل `fast` إلى `0`.
الخطوة 2: ينتقل `slow` إلى `0`، وينتقل `fast` إلى `-4` ثم إلى `2`.
الخطوة 3: ينتقل `slow` إلى `-4`، و`fast` إلى `0` ثم إلى `-4`. إنه الـ~node~ نفسه، لذا اطبع `yes`.
خذ `1 2 1 2` مع `pos` يساوي `-1`. في الخطوة 2، يتجاوز `fast` آخر ~node~ إلى لا شيء، لذا اطبع `no`.

## طريقة التفكير في الحل
أنشئ الـ~nodes~ في ~array~، واربط كل واحد منها بالذي يليه، وإذا لم يكن `pos` يساوي `-1`، فاجعل آخر ~node~ يشير إلى الـ~node~ الموجود في الموضع `pos`. ابدأ `slow` و`fast` من الـ~head~. ما دام كل من `fast` و`fast.next` يشير إلى ~node~، حرّك الاثنين، ثم تحقق هل هما الـ~node~ نفسه. إذا انتهى الـ~loop~، فلا يوجد ~cycle~.

## الـ~complexity~
الـ~time complexity~: `O(n)`. يدخل `slow` الحلقة خلال `n` خطوة، ويلحق به `fast` خلال دورة إضافية واحدة.
الـ~space complexity~: `O(1)` إضافية؛ و`O(n)` عند استخدام ~set~ من الـ~nodes~ التي تمت زيارتها.

## انتبه إلى
- قارن الـ~nodes~، لا القيم. في `1 2 1 2` تتكرر القيم دون وجود ~cycle~. استخدم `is` في ~Python~، و`==` على الـ~objects~ في ~Java~، و`===` في ~JavaScript~، و~pointer equality~ في ~C++~.
- قارن بعد التحريك: يبدأ الاثنان من الـ~head~، لذا الفحص قبل التحريك يعطي `yes` دائمًا.
- ~node~ يرتبط بنفسه. مع `n = 1` و`pos = 0`، الإجابة `yes`.
- `n = 0`. بلا أي ~node~، الإجابة `no`.

الإجابة اعتمادًا على `pos` وحده تجتاز المسألة أدناه؛ ومع ذلك اكتب الـ~fast and slow pointers~.
TEXT,
    ],
    'valid-anagram' => [
        'title' => 'Valid anagram',
        'body' => <<<'TEXT'
الـ~hash table~ يخزن القيم حسب الـ~key~. الـ~hash function~ يحوّل كل ~key~ إلى موضع، لذا فإن البحث عن ~key~ أو إضافته أو تحديثه يستغرق `O(1)` في المتوسط. استخدم `dict` في ~Python~، و`HashMap` في ~Java~، و`Map` في ~JavaScript~، و`unordered_map` في ~C++~. في درس ~Two Sum~ استُخدم لتذكّر المواضع؛ أما هنا فهو يعدّ.

تكون الكلمتان ~anagrams~ عندما تستخدمان الحروف نفسها بعدد المرات نفسه. عُدّ حروف الكلمة الأولى، ثم اطرح منها حروف الكلمة الثانية.

## مثال
خذ `"anagram"` و`"nagaram"`، وفي كل منهما `7` أحرف.
عدّ حروف `"anagram"` يعطي `a: 3, n: 1, g: 1, r: 1, m: 1`.
اطرح `n, a, g, a, r, a, m`. ينتهي عدد مرات كل حرف عند `0`، لذا اطبع `yes`.
خذ `"rat"` و`"car"`. عدّ حروف `"rat"` يعطي `r: 1, a: 1, t: 1`. اطرح `c`: لم يُعدّ أبدًا، لذا ينزل إلى `-1`. اطبع `no`.

## طريقة التفكير في الحل
قارن الطولين أولًا؛ الطولان المختلفان لا يتطابقان أبدًا. ثم أضف `1` لكل حرف من `s`، واطرح `1` لكل حرف من `t`. إذا أصبح عدد مرات أحد الحروف أقل من `0`، فإن `t` يستخدم ذلك الحرف أكثر من `s`. مع طولين متساويين وبلا عدد سالب، يكون عدد مرات كل حرف `0` والإجابة `yes`.

مقارنة نسختين بعد الـ~sorting~ تعمل أيضًا، في `O(n log n)`. مع `1000` حرف تجتاز الطريقتان الاختبارات، لذا لا يستطيع نظام التصحيح التمييز بينهما. اكتب نسخة العدّ مع ذلك: الدرسان التاليان يبنيان عليها.

## الـ~complexity~
الـ~time complexity~: `O(n)` في المتوسط، تحديث واحد في الـ~map~ لكل حرف.
الـ~space complexity~: `O(1)`، لأن الـ~map~ يحمل `26` من الـ~keys~ على الأكثر.

## انتبه إلى
- الحروف نفسها بأعداد مختلفة. `"aab"` و`"abb"` لهما الطول نفسه والحروف نفسها، لكن الإجابة `no`.
- الأسطر الفارغة. يمكن أن تكون أي من الكلمتين فارغة؛ والكلمتان الفارغتان ~anagrams~. اقرأ أسطرًا كاملة كما في درس عكس الـ~string~: `nextLine()` في ~Java~، و`getline` في ~C++~. أما `next()` و`cin >> s` فيتخطيان السطر الفارغ. وفي ~JavaScript~، قسّم الـ~input~ إلى أسطر قبل تطبيق ~trim~ عليها.

عُدّ باستخدام ~map~ في المسألة أدناه، واختبر كلمة فارغة أولًا.
TEXT,
    ],
    'longest-substring-without-repeating-characters' => [
        'title' => 'Longest substring without repeating characters',
        'body' => <<<'TEXT'
الـ~sliding window~ جزء من الـ~string~ بين الموضعين `left` و`right`، يتقدم إلى الأمام بدلًا من أن يُبنى من جديد. وهو هنا لا يحتوي على أي حرف مكرر. كل خطوة تضيف الحرف عند `right`؛ وإذا كان الحرف موجودًا في الـ~window~ من قبل، يقفز `left` إلى ما بعد نسخته السابقة مباشرة. ~hash map~ من الحرف إلى آخر موضع ظهر فيه يجد تلك النسخة في `O(1)`.

## مثال
خذ `"abcabcbb"`، مع `left = 0` و`best = 0`.
من `right = 0` إلى `2`: الحروف `a` و`b` و`c` جديدة. الـ~window~ هو `"abc"`، لذا `best = 3`.
`right = 3`، الحرف `a`: آخر ظهور له عند `0`، لذا `left = 1`. الـ~window~ هو `"bca"`.
`right = 4`، الحرف `b`: آخر ظهور له عند `1`، لذا `left = 2`. الـ~window~ هو `"cab"`.
`right = 5`، الحرف `c`: آخر ظهور له عند `2`، لذا `left = 3`. الـ~window~ هو `"abc"`.
`right = 6`، الحرف `b`: آخر ظهور له عند `4`، لذا `left = 5`. الـ~window~ هو `"cb"`.
`right = 7`، الحرف `b`: آخر ظهور له عند `6`، لذا `left = 7`. الـ~window~ هو `"b"`.
اطبع `3`.

## طريقة التفكير في الحل
لكل `right`: إذا كان آخر ظهور لحرفه عند `p >= left`، فاجعل `left = p + 1`. ثم خزّن `right` على أنه آخر موضع لهذا الحرف، وحدّث `best` بالقيمة `right - left + 1`. لا يتحرك `left` إلا إلى الأمام، لذا يدخل كل حرف الـ~window~ ويخرج منه مرة واحدة.

التوسع من كل بداية حتى يتكرر حرف يجتاز الاختبارات أيضًا، لأنه لا يوجد ~window~ يحمل أكثر من `26` حرفًا. لا يستطيع نظام التصحيح هنا أن يفرض استخدام الـ~window~؛ لكن اكتبه مع ذلك، لأنه يبقى `O(n)` مع أي أبجدية.

## الـ~complexity~
الـ~time complexity~: `O(n)`، ~lookup~ واحد وتحديث واحد لكل حرف.
الـ~space complexity~: `O(1)`، `26` من الـ~entries~ على الأكثر.

## انتبه إلى
- المواضع القديمة. في `"abba"` كان آخر ظهور للحرف `a` عند `0`، لكن `left` يساوي `2` بالفعل. القفز إلى الخلف إلى `1` يعطي `3` بدلًا من `2`؛ و`"tmmzuxt"` يعطي `6` بدلًا من `5`. حرّك `left` إلى الأمام فقط.
- تفريغ الـ~window~ عند التكرار. هذا يحذف حروفًا ما زالت صالحة: `"dvdf"` يعطي `2`، لكن طول `"vdf"` هو `3`.
- سطر فارغ. الإجابة `0`.

اكتب الـ~window~ في المسألة أدناه، وجرّب `abba` أولًا.
TEXT,
    ],
    'subarray-sum-equals-k' => [
        'title' => 'Subarray sum equals K',
        'body' => <<<'TEXT'
الـ~prefix sum~ هو الـ~running total~ حتى الآن. إذا كان المجموع الآن `sum` وكان سابقًا `p`، فإن مجموع الأعداد بينهما هو `sum - p`. لذا يكون مجموع ~subarray~ ينتهي هنا مساويًا `k` إذا وفقط إذا كان أحد المجاميع السابقة يساوي `sum - k`. ~hash map~ من كل مجموع إلى عدد مرات ظهوره يعدّ هذه الحالات في `O(1)` في المتوسط.

## مثال
خذ `1 2 3` مع `k = 3`. يبدأ الـ~map~ بالقيمة `{0: 1}`.
اقرأ `1`: `sum = 1`. ابحث عن `-2`: غير موجود. خزّن `1`.
اقرأ `2`: `sum = 3`. ابحث عن `0`: ظهر مرة واحدة، لذا `count = 1` (الـ~subarray~ `1 2`). خزّن `3`.
اقرأ `3`: `sum = 6`. ابحث عن `3`: ظهر مرة واحدة، لذا `count = 2` (الـ~subarray~ `3`). خزّن `6`.
اطبع `2`.

## طريقة التفكير في الحل
لكل عدد، أضفه إلى `sum`، وأضف إلى الإجابة عدد المرات المخزن للقيمة `sum - k`، وبعد ذلك فقط أضف `1` إلى عدد مرات `sum`. البحث قبل التخزين، كما في ~Two Sum~، يمنع احتساب الـ~subarrays~ الفارغة، حتى عندما `k = 0`.

يبدأ الـ~map~ بالقيمة `{0: 1}` لأن المجموع قبل أول عدد يساوي `0`: وهو الـ~prefix~ الفارغ. بدونها، تضيع الـ~subarrays~ التي تبدأ من الموضع `0`؛ مع `1 2 3`، يضيع الـ~subarray~ `1 2` وتكون الإجابة `1`.

الـ~sliding window~ لا ينجح هنا: مع الأعداد السالبة، توسيع الـ~window~ قد يُنقص المجموع.

استخدام ~loop~ لكل بداية وآخر لكل نهاية يستغرق `O(n^2)`، أي نحو `500000` مجموع عندما `n = 1000`، ويجتاز الاختبارات هنا. لا يستطيع نظام التصحيح أن يفرض استخدام الـ~map~ مع هذا الحجم؛ لكن اكتبه مع ذلك.

## الـ~complexity~
الـ~time complexity~: `O(n)` في المتوسط، ~lookup~ واحد وتحديث واحد لكل عدد.
الـ~space complexity~: `O(n)` من أجل الـ~map~.

## انتبه إلى
- الأصفار. في `0 0 0` مع `k = 0`، يوجد `6` من الـ~subarrays~، ومجموع كل واحد منها يساوي `0`.
- أضف عدد المرات المخزن، لا `1`، وخزّن عدد المرات، لا المواضع: قد يطابق مجموع واحد عدة مجاميع سابقة.

استخدم الـ~map~ في المسألة أدناه، وجرّب `0 0 0` مع `k = 0` أولًا.
TEXT,
    ],
    'height-of-a-bst' => [
        'title' => 'ارتفاع الـ BST',
        'body' => <<<'TEXT'
الـ~binary tree~ يتكون من ~nodes~، لكل منها قيمة و~pointers~ اثنان إلى الـ~children~، هما `left` و`right` (لا شيء إذا لم يوجد ~child~). الـ~node~ الأعلى هو الـ~root~.

الـ~binary search tree~، أو ~BST~، يحافظ على قاعدة واحدة عند كل ~node~: القيم الأصغر تذهب إلى الـ~subtree~ الأيسر، والأكبر إلى الأيمن. لإدخال `v`، ابدأ من الـ~root~، واذهب يسارًا إذا كانت `v` أصغر من الـ~node~، ويمينًا إذا كانت أكبر، حتى يصبح ذلك الـ~pointer~ لا شيء. ضع الـ~node~ الجديد هناك.

لا يُعاد توازن الـ~tree~ أبدًا، لذا يعتمد شكله على ترتيب الإدخال: `1 2 3 4 5 6 7` يعطي سلسلة من `7` ~nodes~.

## مثال
أدخل `4 2 6 1 3 5 7`. `4` هو الـ~root~، و`2` و`6` هما الـ~children~ الخاصان به، و`1` و`3` تحت `2`، و`5` و`7` تحت `6`. كل مسار من الـ~root~ إلى الأسفل، مثل `4 2 1`، فيه `3` ~nodes~، لذا الارتفاع `3`.
أدخل `5 3 8 1 4 7 9 2`: أطول مسار، `5 3 1 2`، يعطي الارتفاع `4`.

## طريقة التفكير في الحل
ارتفاع الـ~tree~ يساوي `1` زائد الارتفاع الأكبر بين الـ~subtrees~ الاثنين، والـ~tree~ الفارغ ارتفاعه `0`: ~recursive function~ من ثلاثة أسطر.

بدون ~recursion~، نفّذ ~push~ للزوج `(root, 1)` في ~stack~. نفّذ ~pop~ لزوج، واحتفظ بأكبر عمق ظهر، ونفّذ ~push~ لكل ~child~ مع `depth + 1`. ويعمل أيضًا ~queue~ يعالج مستوى واحدًا في كل مرة.

`1,000` قيمة مرتبة تعطي سلسلة بعمق `1,000`. عندها تتداخل استدعاءات الـ~recursion~ في الإدخال أو في حساب الارتفاع نحو `1,000` استدعاء، وهذا يتجاوز الـ~recursion limit~ الافتراضي في ~Python~ وهو `1000`، فيتوقف البرنامج مع `RecursionError`. أدخل باستخدام ~loop~ واستخدم الـ~stack~، أو استدعِ `sys.setrecursionlimit(10000)` أولًا.

## الـ~complexity~
الـ~time complexity~: `O(n * h)` للبناء، حيث `h` هو الارتفاع: `O(n^2)` مع ~input~ مرتب. و`O(n)` لحساب الارتفاع.
الـ~space complexity~: `O(n)`.

## انتبه إلى
- عُدّ الـ~nodes~، لا الـ~edges~. الـ~node~ الواحد ارتفاعه `1`، والـ~tree~ الفارغ ارتفاعه `0`.
- `n = 0`. لا يوجد سطر ثانٍ ولا ~root~.

نظام التصحيح لا يرى إلا العدد، لذا لا يمكنه التمييز بين استخدام الـ~stack~ ورفع الـ~recursion limit~.
TEXT,
    ],
    'lowest-common-ancestor-of-a-bst' => [
        'title' => 'Lowest common ancestor of a BST',
        'body' => <<<'TEXT'
في الـ~BST~، القيم الأصغر من ~node~ تكون في الـ~subtree~ الأيسر له، والأكبر في الأيمن، لذا يخبرك كل ~node~ في أي جهة تقع القيمة.

الـ~lowest common ancestor~، أو ~LCA~، للقيمتين `p` و`q` هو أعمق ~node~ يحتوي الـ~subtree~ الخاص به على الاثنين. يُعد الـ~node~ ~ancestor~ لنفسه: إذا كانت `p` فوق `q`، أو `p = q`، فالإجابة `p`.

## مثال
أدخل `6 2 8 0 4 7 9 3 5`. `6` هو الـ~root~، وتحته `2` و`8`؛ و`0` و`4` تحت `2`، و`3` و`5` تحت `4`، و`7` و`9` تحت `8`.
`p = 2`، `q = 8`: `2` أصغر من `6` و`8` أكبر، لذا ينفصلان عند الـ~root~. اطبع `6`.
`p = 2`، `q = 5`: عند `6` كلاهما أصغر، اذهب يسارًا. عند `2`، `p` هو الـ~node~ نفسه. اطبع `2`.
`p = 5`، `q = 3`: عند `6` اذهب يسارًا؛ عند `2` كلاهما أكبر، اذهب يمينًا. عند `4`، `5` أكبر و`3` أصغر. اطبع `4`.

## طريقة التفكير في الحل
انزل من الـ~root~. إذا كانت `p` و`q` كلتاهما أصغر من الـ~node~، فاذهب يسارًا؛ وإذا كانتا كلتاهما أكبر، فاذهب يمينًا. وإلا فهذا الـ~node~ هو نقطة الانفصال: إما أنهما في جهتين مختلفتين، أو أن إحداهما هي الـ~node~ نفسه. اطبعه.

مقارنة القيمتين في كل خطوة تتعامل مع حالة `p = q` ومع أي ترتيب، دون ~recursion~ أو ~parent pointers~. في ~binary tree~ عادي، بدون قاعدة الـ~BST~، ستحتاج إلى البحث في الـ~subtrees~ الاثنين.

## الـ~complexity~
الـ~time complexity~: `O(n * h)` للبناء، حيث `h` هو الارتفاع، ثم `O(h)` للنزول.
الـ~space complexity~: `O(n)` من أجل الـ~nodes~، و`O(1)` للنزول.

## انتبه إلى
- الترتيب. لا تفترض أن `p < q`.
- المساواة. الـ~node~ الذي يساوي `p` أو `q` هو نقطة الانفصال.
- الـ~trees~ العميقة. الـ~input~ المرتب يعطي سلسلة بعمق `1,000`، وهذا يتجاوز الـ~recursion limit~ الافتراضي في ~Python~ وهو `1000`، لذا أدخل باستخدام ~loop~.

نظام التصحيح لا يرى إلا القيمة، لذا فإن مقارنة المسار من الـ~root~ إلى `p` بالمسار من الـ~root~ إلى `q` تجتاز الاختبارات أيضًا. اكتب النزول.
TEXT,
    ],
    'delete-from-a-bst' => [
        'title' => 'الحذف من الـ BST',
        'body' => <<<'TEXT'
يجب أن يحافظ الحذف على قاعدة الـ~BST~. جِد الـ~node~ الذي قيمته `x` والـ~parent~ الخاص به، ثم:
- بلا ~children~: اجعل رابط الـ~parent~ لا شيء.
- ~child~ واحد: اجعل رابط الـ~parent~ يشير إلى ذلك الـ~child~.
- ~children~ اثنان: جِد الـ~successor~، أي أصغر قيمة في الـ~subtree~ الأيمن: تحرك يمينًا مرة واحدة، ثم يسارًا ما دام ذلك ممكنًا. انسخ قيمته إلى الـ~node~، ثم احذف الـ~node~ الخاص بالـ~successor~. ليس له ~child~ أيسر، لذا تحذفه إحدى الحالتين الأوليين.

الـ~root~ ليس له ~parent~: حذفه يجعل الـ~child~ الخاص به، أو لا شيء، هو الـ~root~ الجديد.

## مثال
أدخل `50 30 70 20 40 60 80 65`، ثم احذف `50`، وهو الـ~root~، وله ~children~ اثنان.
تحرك يمينًا إلى `70`، ثم يسارًا إلى `60`. الـ~node~ `60` ليس له ~child~ أيسر، لذا فهو الـ~successor~.
انسخ `60` إلى الـ~root~. الـ~node~ القديم `60` له ~child~ واحد، `65`، لذا أصبح الرابط الأيسر في `70` يشير الآن إلى `65`.
الـ~preorder~ يطبع الـ~root~، ثم الـ~subtree~ الأيسر، ثم الأيمن: `60 30 20 40 70 65 80`.
أما حذف `20`، وهو ~leaf~، بدلًا من ذلك فيعطي `50 30 40 70 60 65 80`.

## طريقة التفكير في الحل
أثناء البحث، خزّن `parent` و`cur`، وكذلك الـ~parent~ الخاص بالـ~successor~. إذا كان `cur.right` هو الـ~successor~ نفسه، فاجعل `cur.right` يشير إلى الـ~child~ الأيمن الخاص بالـ~successor~؛ وإلا فاجعل `left` في الـ~parent~ الخاص بالـ~successor~ يشير إلى ذلك الـ~child~ الأيمن.

لطباعة الـ~preorder~، استخدم ~stack~: نفّذ ~pop~ على ~node~ واطبعه، ثم نفّذ ~push~ على الـ~child~ الأيمن الخاص به، ثم الأيسر.

## الـ~complexity~
الـ~time complexity~: `O(n * h)` للبناء، حيث `h` هو الارتفاع، و`O(h)` للحذف، و`O(n)` للطباعة.
الـ~space complexity~: `O(n)`.

## انتبه إلى
- القاعدة. استخدام أكبر قيمة في الـ~subtree~ الأيسر بدلًا من الـ~successor~ يترك أيضًا ~BST~ صحيحًا، لكنه مختلف، لذا يفشل هنا.
- نسخ الـ~successor~ دون حذفه يترك قيمته مرتين.
- حذف الـ~node~ الوحيد. اطبع سطرًا فارغًا.
- الـ~trees~ العميقة. الـ~input~ المرتب يعطي سلسلة بعمق `1,000`، وهذا يتجاوز الـ~recursion limit~ الافتراضي في ~Python~.

نظام التصحيح لا يقارن إلا الـ~preorder~، لذا لا يمكنه رؤية هل حرّكت الـ~pointers~ أم لا. حرّكها مع ذلك.
TEXT,
    ],
    'last-stone-weight' => [
        'title' => 'Last stone weight',
        'body' => <<<'TEXT'
الـ~heap~ هو ~array~ يتفوق فيه كل ~parent~ على الـ~children~ الخاصين به، لذا يكون `a[0]` أكبر عنصر (~max-heap~) أو أصغر عنصر (~min-heap~). للعنصر `a[i]` ~children~ هما `a[2i + 1]` و`a[2i + 2]`، و~parent~ هو `a[(i - 1) / 2]`، مقرّبًا إلى الأسفل.

## مثال
~max-heap~، نفّذ ~push~ للقيم `2 7 4 8`:
`[2 7]` يصبح `[7 2]`.
نفّذ ~push~ للقيمة `4`: `[7 2 4]`.
`[7 2 4 8]`: `8` يتفوق على `2`، ثم على `7`: `[8 7 4 2]`.
نفّذ ~pop~ للقيمة `8`: `[2 7 4]` يصبح `[7 2 4]`.

## طريقة التفكير في الحل
الـ~push~ يضيف العنصر في النهاية ثم ينفّذ ~sift up~: يبدّله مع الـ~parent~ ما دام العنصر يتفوق عليه. والـ~pop~ يأخذ `a[0]`، وينقل آخر عنصر إلى الأعلى، ثم ينفّذ ~sift down~: يبدّله مع الـ~child~ الأفضل ما دام ذلك الـ~child~ يتفوق عليه.

## ~Python~
    h = []
    for x in [2, 7, 4]:
        heapq.heappush(h, -x)
    print(-heapq.heappop(h))

## ~Java~
    PriorityQueue<Integer> h = new PriorityQueue<>(Collections.reverseOrder());
    h.addAll(List.of(2, 7, 4));
    System.out.println(h.poll());

## ~JavaScript~
    class Heap {
      constructor(less) { this.less = less; this.a = [] }
      push(x) {
        const a = this.a
        let i = a.push(x) - 1
        for (let p; i > 0 && this.less(x, a[p = (i - 1) >> 1]); i = p) a[i] = a[p]
        a[i] = x
      }
      pop() {
        const a = this.a, top = a[0], x = a.pop()
        let i = 0
        for (let c; (c = 2 * i + 1) < a.length; i = c) {
          if (c + 1 < a.length && this.less(a[c + 1], a[c])) c++
          if (!this.less(a[c], x)) break
          a[i] = a[c]
        }
        if (a.length) a[i] = x
        return top
      }
    }
    const h = new Heap((x, y) => x > y)  // max-heap
    for (const x of [2, 7, 4]) h.push(x)
    console.log(h.pop())

## ~C++~
    priority_queue<int> h;
    for (int x : {2, 7, 4}) h.push(x);
    cout << h.top();

## الـ~complexity~
الـ~push~ والـ~pop~ يكلفان `O(log n)`: تبديل واحد لكل مستوى، ونحو `log2 n` مستوى. والـ~peek~ يكلف `O(1)`.

## انتبه إلى
- `heapq` في ~Python~ هو ~min-heap~ فقط: نفّذ ~push~ للقيمة `-x`.
- `PriorityQueue` في ~Java~ هو ~min-heap~: استخدم `Collections.reverseOrder()`. الـ~comparators~ من نوع `b - a` قد يحدث فيها ~overflow~ (انظر درس ~Running median~).
- `priority_queue` في ~C++~ هو ~max-heap~.
- لا يوجد ~heap~ في ~JavaScript~: استخدم هذا الـ~class~.
TEXT,
    ],
    'merge-k-sorted-lists' => [
        'title' => 'Merge k sorted lists',
        'body' => <<<'TEXT'
احتفظ بأول عنصر من كل ~list~ في ~min-heap~: أصغر عدد متبقٍ يكون دائمًا أول عنصر في إحدى الـ~lists~. نفّذ ~pop~ له، وأخرجه إلى الـ~output~، ثم نفّذ ~push~ للعدد التالي من الـ~list~ الخاص به.

## مثال
`A = 1 4 5`، و`B = 1 3 4`، و`C = 2 6`. البداية: `1 1 2`.
~pop~ للقيمة `1`، و~push~ للقيمة `4` من `A`. ~pop~ للقيمة `1`، و~push~ للقيمة `3` من `B`. ~pop~ للقيمة `2`، و~push~ للقيمة `6` من `C`.
~pop~ للقيمة `3`، و~push~ للقيمة `4` من `B`. ~pop~ للقيمة `4`، و~push~ للقيمة `5` من `A`. ~pop~ للقيمة `4`؛ انتهى `B`. ثم `5` و`6`.
الـ~output~: `1 1 2 3 4 4 5 6`.

## طريقة التفكير في الحل
كل ~entry~ `(value, list index, position)` يشير إلى العدد التالي. الـ~lists~ الفارغة لا تدخل أبدًا.

## ~Python~
    heap = [(l[0], i, 0) for i, l in enumerate(lists) if l]
    heapq.heapify(heap)
    while heap:
        v, i, j = heapq.heappop(heap)
        out.append(v)
        if j + 1 < len(lists[i]):
            heapq.heappush(heap, (lists[i][j + 1], i, j + 1))

## ~Java~
    PriorityQueue<int[]> h = new PriorityQueue<>((x, y) -> Integer.compare(x[0], y[0]));
    for (int i = 0; i < k; i++)
        if (lists[i].length > 0) h.add(new int[] {lists[i][0], i, 0});
    while (!h.isEmpty()) {
        int[] t = h.poll();
        out.add("" + t[0]);
        int i = t[1], j = t[2] + 1;
        if (j < lists[i].length) h.add(new int[] {lists[i][j], i, j});
    }

## ~JavaScript~
    const h = new Heap((x, y) => x[0] < y[0])  // Last stone weight's class
    lists.forEach((l, i) => l.length && h.push([l[0], i, 0]))
    while (h.a.length) {
      const [v, i, j] = h.pop()
      out.push(v)
      if (j + 1 < lists[i].length) h.push([lists[i][j + 1], i, j + 1])
    }

## ~C++~
    priority_queue<array<int, 3>, vector<array<int, 3>>, greater<>> h;
    for (int i = 0; i < k; i++)
        if (!lists[i].empty()) h.push({lists[i][0], i, 0});
    while (!h.empty()) {
        auto [v, i, j] = h.top();
        h.pop();
        out.push_back(v);
        if (j + 1 < (int)lists[i].size()) h.push({lists[i][j + 1], i, j + 1});
    }

## الـ~complexity~
مع `k` من الـ~entries~ على الأكثر، يكلف الـ~push~ والـ~pop~ `O(log k)`؛ ويكلف الـ~peek~ `O(1)`. ومع `N` عددًا: `O(N log k)`.

## انتبه إلى
- ~Python~: مع ~entries~ على شكل `(value, iterator)`، التعادل في `value` يجعل الـ~iterators~ تُقارن، وهذا يجعل البرنامج يتعطل (وكذلك الـ~nodes~). نفّذ ~push~ للثلاثي `(value, list index, position)`.
TEXT,
    ],
    'running-median' => [
        'title' => 'Running median',
        'body' => <<<'TEXT'
احتفظ بالنصف الأصغر في `lo`، وهو ~max-heap~، وبالنصف الأكبر في `hi`، وهو ~min-heap~؛ يكون حجم `lo` مساويًا لحجم `hi` أو أكبر منه بواحد. الوسيط هو الـ~top~ في `lo` عندما يكون عدد القيم فرديًا، وإلا فهو متوسط قيمتي الـ~top~ في الاثنين.

## مثال
أضف `2 7 -4 3 10` (`lo | hi: median`):
`2 | (empty): 2`
`2 | 7: 4.5`
`2 -4 | 7: 2`
`2 -4 | 3 7: 2.5`
`3 2 -4 | 7 10: 3`

## طريقة التفكير في الحل
لإضافة `x`، نفّذ ~push~ له في `lo`، ثم انقل الـ~top~ من `lo` إلى `hi`؛ وإذا أصبح `hi` أكبر، فانقل الـ~top~ منه عائدًا إلى `lo`.

## ~Python~
    def add(x):  # lo holds -x
        heapq.heappush(lo, -x)
        heapq.heappush(hi, -heapq.heappop(lo))
        if len(hi) > len(lo):
            heapq.heappush(lo, -heapq.heappop(hi))

    def median():
        if len(lo) > len(hi):
            return str(-lo[0])
        return str((hi[0] - lo[0]) / 2).removesuffix(".0")

## ~Java~
    static void add(int x) {
        lo.add(x);
        hi.add(lo.poll());
        if (hi.size() > lo.size()) lo.add(hi.poll());
    }

    static String median() {
        if (lo.size() > hi.size()) return "" + lo.peek();
        long s = (long) lo.peek() + hi.peek();
        return new BigDecimal(s).divide(BigDecimal.valueOf(2)).toPlainString();
    }

## ~JavaScript~
    function add(x) {
      lo.push(x)
      hi.push(lo.pop())
      if (hi.a.length > lo.a.length) lo.push(hi.pop())
    }

    function median() {
      if (lo.a.length > hi.a.length) return String(lo.a[0])
      return String((lo.a[0] + hi.a[0]) / 2)
    }

## ~C++~
    void add(long long x) {
        lo.push(x);
        hi.push(lo.top());
        lo.pop();
        if (hi.size() > lo.size()) {
            lo.push(hi.top());
            hi.pop();
        }
    }

    string median() {
        if (lo.size() > hi.size()) return to_string(lo.top());
        long long s = lo.top() + hi.top();
        return (s < 0 ? "-" : "") + to_string(llabs(s) / 2) + (s % 2 ? ".5" : "");
    }

## الـ~complexity~
كل عدد يكلف `O(log n)`: بضع عمليات ~push~ و~pop~ بتكلفة `O(log n)` لكل منها، ثم ~peek~ على قيمتي الـ~top~، `O(1)`.

## انتبه إلى
- العدد الزوجي من القيم قد يعطي `.5`، والـ~integer division~ يقرّب الأعداد السالبة بشكل خطأ: `(-3 + -2) / 2` يساوي `-2.5`، لا `-2` (~Java~ و~C++~) ولا `-3` (`//` في ~Python~). مع المجموع `s`، اطبع الإشارة، ثم `|s| / 2`، ثم `.5` إذا كان `s` فرديًا.
- يحدث ~overflow~ في الأعداد من نوع `int` بحجم ~32-bit~: `2147483647 + 2147483646`، و`b - a` مع `2147483647` و`-2147483648`. استخدم `long` و`reverseOrder()`.
TEXT,
    ],
    'binary-strings' => [
        'title' => 'Binary strings',
        'body' => <<<'TEXT'
الـ~recursive function~ يستدعي نفسه على نسخة أصغر من المسألة نفسها، ويحتاج إلى ~base case~: حالة صغيرة بما يكفي لتُعرف إجابتها دون استدعاء آخر. بدونه لا تتوقف الاستدعاءات أبدًا.

الـ~backtracking~ يبني الإجابة اختيارًا بعد اختيار: اختر خيارًا، ثم استكشف بأن يستدعي الـ~function~ نفسه من أجل الاختيارات الباقية، ثم تراجع عن الاختيار ليبدأ الخيار التالي من الحالة نفسها. اختر، استكشف، تراجع.

هنا تختار كل خطوة الحرف التالي، `0` أو `1`، والـ~base case~ هو ~string~ طوله `n`: اطبعه.

## مثال
خذ `n = 2`، وابدأ من الـ~string~ الفارغ.
اختر `0`: `"0"`. استكشف: اختر `0` مرة أخرى، و`"00"` طوله `2`: اطبعه. تراجع إلى `"0"`، واختر `1`، واطبع `"01"`، ثم تراجع إلى `"0"`.
تراجع عن أول `0`. اختر `1`، فتطبع الخطوات نفسها `"10"` و`"11"`.
الـ~output~ هو `00` و`01` و`10` و`11`.

تشكّل الاستدعاءات ~tree~ اسمه الـ~call tree~: الـ~string~ الفارغ في الـ~root~، و~children~ اثنان لكل استدعاء (`0` و`1`)، والـ~strings~ المكتملة وعددها `2^n` هي الـ~leaves~. عندما `n = 2` يكون عدد الاستدعاءات `1 + 2 + 4 = 7`.

## طريقة التفكير في الحل
احتفظ بالحروف المختارة في ~list~ اسمه `cur`. إذا كان فيه `n` من الحروف، فاطبعها وأنهِ الاستدعاء. وإلا، فمن أجل `0` ثم `1`: أضفه إلى النهاية (اختر)، واستدعِ الـ~function~ نفسه (استكشف)، ثم أزله بعملية ~pop~ (تراجع). تجربة `0` قبل `1` تجعل الأسطر بترتيب تصاعدي.

تمرير `cur + bit` على أنه ~string~ جديد لا يحتاج إلى تراجع، لأن كل استدعاء يحصل على نسخة، لكن الدرسين التاليين يحتاجان إلى طريقة الـ~list~.

## الـ~complexity~
الـ~time complexity~: `O(2^n * n)`: هناك `2^n` من الـ~strings~، طول كل منها `n`، تنتجها `2^(n+1) - 1` من الاستدعاءات.
الـ~space complexity~: `O(n)` من أجل `cur` والـ~call stack~، إضافة إلى الـ~output~.

## انتبه إلى
- الأصفار في البداية. `001` هو ~string~ طوله `3`؛ وطباعة العدد `1` تضيّعها.
- نسيان التراجع. بدون الـ~pop~، يبدأ الفرع الثاني من ~string~ خاطئ.

طباعة الأعداد من `0` إلى `2^n - 1` بالنظام الثنائي، بعدد `n` من الخانات، تجتاز الاختبارات أيضًا؛ نظام التصحيح لا يرى إلا الأسطر. اكتب الـ~recursion~ مع ذلك.
TEXT,
    ],
    'permutations' => [
        'title' => 'Permutations',
        'body' => <<<'TEXT'
الـ~permutation~ للأعداد من `1` إلى `n` يستخدم كل عدد مرة واحدة بالضبط. ابنه موضعًا بعد موضع بنمط اختر، استكشف، تراجع، مع شيئين إضافيين: الإجابة الجزئية `cur`، وفيها الأعداد التي وُضعت حتى الآن، وعلامة `used` لكل عدد، كي يُتخطى أي عدد موجود في `cur` بالفعل.

## مثال
خذ `n = 3`، و`cur` فارغ، ولا عدد مستخدم.
اختر `1`: `cur = [1]`. اختر `2`: `[1, 2]`. اختر `3`: طول `[1, 2, 3]` هو `3`، لذا اطبع `1 2 3`.
تراجع عن `3`، ثم عن `2`: عدنا إلى `[1]`، و`2` و`3` متاحان من جديد. اختر `3`: `[1, 3]`. اختر `2`: اطبع `1 3 2`.
تراجع حتى يصبح `cur` فارغًا. البدء بالعدد `2` يعطي `2 1 3` و`2 3 1`، ثم `3 1 2` و`3 2 1`: أي `3! = 6` في المجموع.

## طريقة التفكير في الحل
إذا كان في `cur` عدد `n` من الأعداد، فاطبعه: هذا هو الـ~base case~. وإلا، فمرّ على `v` من `1` إلى `n`، وتخطَّ `v` إذا كان مستخدمًا. وإذا لم يكن كذلك، فعلّمه مستخدمًا وأضفه إلى النهاية (اختر)، واستدعِ الـ~function~ نفسه (استكشف)، ثم أزله بعملية ~pop~ وامسح علامته (تراجع).

بفضل التراجع، يكفي `cur` واحد و`used` واحد، وهو ~array~، لكل الـ~call tree~. إذا لم تمسح العلامة، تبقى الأعداد مستخدمة بعد الرجوع: عندما `n = 3` لا يُطبع إلا `1 2 3`.

ترتيب تجربة الخيارات يحدد ترتيب الـ~output~. تجربة `1` إلى `n` في كل موضع تعطي الـ~lexicographic order~: كل الـ~permutations~ التي تبدأ بالعدد `1`، ثم `2`، وهكذا. تبديل `cur[i]` مع كل موضع بعده ينتج أيضًا كل الـ~permutations~ وعددها `n!`، لكنه يطبع `3 2 1` قبل `3 1 2`.

## الـ~complexity~
الـ~time complexity~: `O(n! * n)`، أي `720` سطرًا عندما `n = 6`.
الـ~space complexity~: `O(n)`، إضافة إلى الـ~output~.

## انتبه إلى
- الترقيم. اجعل طول `used` يساوي `n + 1` إذا كانت القيمة هي الـ~index~ فيه.
- المسافات. افصل بين الأعداد بمسافة واحدة، دون مسافة في نهاية السطر.

`itertools.permutations` في ~Python~ و`next_permutation` في ~C++~ يطبعان الأسطر الصحيحة أيضًا؛ ونظام التصحيح لا يستطيع التمييز. اكتب الـ~backtracking~: درس ~N-Queens~ يبني عليه.
TEXT,
    ],
    'n-queens' => [
        'title' => 'N-Queens',
        'body' => <<<'TEXT'
الملكة تهاجم على امتداد صفها وعمودها وقطريها. ضع ملكة واحدة في كل صف، وجرّب الأعمدة من `1` إلى `n`، ولا تقبل عمودًا إلا إذا لم تكن في الصفوف التي فوقه ملكة في العمود نفسه أو على أحد قطريه.

هذا الفحص هو الـ~pruning~: يُتخطى العمود المهاجَم، ومعه كل الرقع التي تتفرع عنه. وعندما لا يصلح أي عمود، ارجع: يتراجع الصف الذي فوقه عن ملكته ويجرّب عموده التالي. هذا هو الـ~backtrack~.

على امتداد قطر يتجه إلى الأسفل واليمين تبقى `row - col` ثابتة؛ وعلى قطر يتجه إلى الأسفل واليسار تبقى `row + col` ثابتة. احتفظ بثلاثة ~sets~: للأعمدة المستخدمة، ولقيم `row - col`، ولقيم `row + col`: كل فحص هو ثلاث عمليات ~lookup~.

## مثال
خذ `n = 4`. الصف `1`: العمود `1`. الصف `2`: العمودان `1` و`2` مهاجَمان، لذا العمود `3`.
الصف `3`: العمودان `1` و`3` مأخوذان، و`2` يشارك الصف `2` في `row + col = 5`، و`4` يشاركه في `row - col = -1`. طريق مسدود: ارجع إلى الصف `2`.
الصف `2`: العمود `4`. الصف `3`: العمود `2`. الصف `4`: كل الأعمدة مهاجَمة، وهذا طريق مسدود آخر. لم يبقَ للصفين `3` و`2` أي عمود: ارجع إلى الصف `1`.
الصف `1`: العمود `2`. ثم الأعمدة `4` و`1` و`3`: `2 4 1 3`، وهو أول حل.
ويُعثر أيضًا على `3 1 4 2`: اطبع `2`، ثم `2 4 1 3`.

## طريقة التفكير في الحل
`place(row)`: إذا كانت في كل صف ملكة، فعُدّ الحل واحفظه إن كان الأول. وإلا، فمن أجل كل عمود متاح، أضف `col` و`row - col` و`row + col` (اختر)، واستدعِ `place(row + 1)` (استكشف)، ثم أزل الثلاثة (تراجع). تجربة الأعمدة بدءًا من `1` تجعل أول حل يُعثر عليه هو الحل الذي يُطبع.

## الـ~complexity~
الـ~time complexity~: `O(n! * n)` على الأكثر، لأن عدد الأعمدة المتاحة في كل صف أقل بواحد على الأقل من الصف الذي فوقه، وكل استدعاء يفحص الأعمدة كلها وعددها `n`؛ والـ~pruning~ يجعله أقل من ذلك بكثير: `35,538` عملية وضع عندما `n = 10`.
الـ~space complexity~: `O(n)`.

## انتبه إلى
- تراجع عن العلامات الثلاث كلها، وإلا رأت الصفوف التالية هجمات غير حقيقية.
- `n = 2` و`n = 3` ليس لهما حل: اطبع `0`، ثم `none`.

مع عشرة ~inputs~ فقط، يجتاز جدول فيه الإجابات المعروفة كل الاختبارات. لا يستطيع نظام التصحيح منع ذلك، لكنه يضيّع الهدف من الدرس كله.
TEXT,
    ],
    'count-connected-components' => [
        'title' => 'عدّ الـ connected components',
        'body' => <<<'TEXT'
الـ~graph~ مجموعة من الـ~nodes~ تربط بينها ~edges~. الـ~edges~ هنا ~undirected~: الـ~edge~ `u v` يعمل في الاتجاهين. خزّنه على شكل ~adjacency list~، أي ~list~ من الجيران لكل ~node~: الـ~edge~ `u v` يضيف `v` إلى الـ~list~ الخاص بالـ~node~ `u`، ويضيف `u` إلى الـ~list~ الخاص بالـ~node~ `v`.

الـ~connected component~ مجموعة من الـ~nodes~ يمكن لكل منها الوصول إلى البقية. عُدّ الـ~walks~: ابدأ ~walk~ من كل ~node~ لم تتم زيارته بعد، وعلّم كل ما يصل إليه في ~visited set~.

## مثال
خذ `n = 5` مع الـ~edges~ `1 2` و`2 3` و`4 5`. الـ~lists~ هي `1: [2]`، و`2: [1, 3]`، و`3: [2]`، و`4: [5]`، و`5: [4]`.
الـ~node~ `1` لم تتم زيارته: `count = 1`. يزور الـ~walk~ الـ~nodes~ `1` و`2` و`3`.
تخطَّ `2` و`3`: تمت زيارتهما.
الـ~node~ `4` لم تتم زيارته: `count = 2`. يزور الـ~walk~ `4` و`5`.
تخطَّ `5`. اطبع `2`.

## طريقة التفكير في الحل
مرّ على الـ~nodes~ من `1` إلى `n`. عند ~node~ لم تتم زيارته، أضف `1` إلى `count`، وعلّمه، ونفّذ ~push~ له على ~stack~. ثم أخرج الـ~nodes~ بعملية ~pop~ حتى يصبح الـ~stack~ فارغًا، ومع كل ~node~ نفّذ ~push~ لكل جار لم تتم زيارته بعد وعلّمه، كي لا يدخل أي ~node~ مرتين. هذا هو الـ~depth-first search~ (~DFS~)؛ واستخدام ~queue~ بدلًا من الـ~stack~ يعطي الـ~breadth-first search~ (~BFS~). كلاهما يصل إلى الـ~nodes~ نفسها.

الـ~union-find~، الذي يدمج المجموعات ~edge~ بعد ~edge~، يجتاز الاختبارات أيضًا؛ نظام التصحيح لا يرى إلا العدد.

## الـ~complexity~
الـ~time complexity~: `O(n + m)`: يُنفَّذ ~push~ لكل ~node~ مرة واحدة، ويُقرأ كل ~edge~ مرتين.
الـ~space complexity~: `O(n + m)` من أجل الـ~lists~ والعلامات.

## انتبه إلى
- الـ~nodes~ التي ليس لها ~edges~. كل منها ~component~ مستقل: `4` من الـ~nodes~ بلا ~edges~ تعطي `4`.
- الـ~edges~ المكررة والـ~self-loops~. الـ~edge~ `1 2` مرتين، أو `3 3`، لا يضران عندما تتحقق من الزيارة.
- الـ~recursion~ العميق. الـ~recursive DFS~ على المسار `1-2-...-1000` يُنتج `1000` استدعاء متداخلًا، وهذا يتجاوز الحد الافتراضي في ~Python~. استخدم الـ~stack~.
- الترقيم. الـ~nodes~ من `1` إلى `n`، لذا اجعل طول الـ~lists~ `n + 1`.

عُدّ الـ~walks~ في المسألة أدناه.
TEXT,
    ],
    'number-of-islands' => [
        'title' => 'Number of islands',
        'body' => <<<'TEXT'
الشبكة هي ~graph~ لا تحتاج أبدًا إلى بنائه. كل خلية ~node~، وجيرانها هي الخلايا التي فوقها وتحتها وعلى يسارها وعلى يمينها. احتفظ بالحركات الأربع على شكل ~offsets~، `(-1, 0)` و`(1, 0)` و`(0, -1)` و`(0, 1)`، تُضاف إلى الصف والعمود. لا تُحسب الحركة إلا إذا كانت الخلية الجديدة داخل الشبكة: `0 <= row < r` و`0 <= col < c`.

الجزيرة مجموعة من الخلايا المتصلة التي قيمتها `1`، لذا فعدّ الجزر هو عدّ الـ~components~.

## مثال
خذ هذه الشبكة بحجم `4` في `5`:
    11000
    11000
    00100
    00011
امسح الشبكة صفًا بعد صف. في `(0, 0)` توجد `1` لم تتم زيارتها: `count = 1`. يعلّم البحث `(0, 0)` و`(0, 1)` و`(1, 0)` و`(1, 1)`.
في `(2, 2)` توجد `1` لم تتم زيارتها، وليس لها جيران قيمتها `1`: `count = 2`.
في `(3, 3)` توجد `1` لم تتم زيارتها: `count = 3`. يعلّم البحث `(3, 3)` و`(3, 4)`.
اطبع `3`.

## طريقة التفكير في الحل
مرّ على كل خلية. عند `1` لم تتم زيارتها بعد، أضف `1` إلى `count` وابحث بدءًا منها باستخدام ~stack~ أو ~queue~، وعلّم كل `1` تصل إليها: في شبكة تسجّل الزيارة، أو بتغييرها إلى `0`.

الـ~recursive DFS~ أقصر، لكنه على شبكة `50` في `50` كلها `1` يُنتج نحو `2,500` استدعاء متداخلًا. الـ~recursion limit~ الافتراضي في ~Python~ هو `1000`، لذا يتوقف البرنامج مع `RecursionError`. استخدم ~stack~ صريحًا أو ~queue~، أو ارفع الحد باستخدام `sys.setrecursionlimit`. في نظام التصحيح هذا، تحمّلت ~Java~ و~JavaScript~ و~C++~ `2,500` استدعاء متداخلًا، لكن الـ~stack~ آمن في كل مكان.

## الـ~complexity~
الـ~time complexity~: `O(r * c)`: كل خلية تُعالج بضع مرات.
الـ~space complexity~: `O(r * c)` من أجل العلامات والـ~stack~.

## انتبه إلى
- الحدود. تحقق منها قبل قراءة أي خلية.
- الخلايا على القطر ليست جيرانًا. في الشبكة `101`، `010`، `101` تتلامس قيم `1` عند الزوايا فقط، لذا الإجابة `5`.
- علّم الخلية عند الـ~push~، وإلا فقد تدخل الخلية الـ~stack~ أكثر من مرة.

نظام التصحيح لا يتحقق إلا من العدد. جرّب شبكة كاملة من `1` قبل الإرسال.
TEXT,
    ],
    'shortest-path-in-a-grid' => [
        'title' => 'أقصر مسار في الشبكة',
        'body' => <<<'TEXT'
الـ~breadth-first search~ (~BFS~) يستكشف على شكل حلقات: البداية، ثم كل خلية تبعد حركة واحدة، ثم كل خلية تبعد حركتين، وهكذا. الـ~queue~ يحقق ذلك: تخرج الخلايا بالترتيب الذي دخلت به. لذا فأول مرة يصل فيها الـ~BFS~ إلى خلية، يكون قد استخدم أقل عدد من الحركات. خزّن هذا العدد في `dist`، وهي شبكة كل قيمها في البداية `-1`.

## مثال
خذ هذه الشبكة بحجم `4` في `5`، حيث `1` جدار:
    00000
    00000
    00001
    00100
`dist[0][0] = 0`. حلقة بعد حلقة، تأخذ `(2, 3)` القيمة `5`، و`(3, 3)` القيمة `6`، والنهاية `(3, 4)` القيمة `7`. اطبع `7`.
الـ~DFS~ الذي يجرّب الأعلى، ثم الأسفل، ثم اليسار، ثم اليمين، بهذا الترتيب، ينزل في العمود `0`، ويصعد في العمود `1`، وينزل في العمود `2`، ولا يصل إلى النهاية أول مرة إلا بعد `13` حركة.

## طريقة التفكير في الحل
إذا كانت البداية أو النهاية جدارًا، فاطبع `-1`. وإلا فاجعل `dist` للبداية `0` وأضفها إلى الـ~queue~. خذ الخلايا من مقدمة الـ~queue~؛ ومن أجل كل جار داخل الشبكة، مفتوح، وما زالت قيمته `-1`، اجعل `dist` له مساويًا لقيمة `dist` الحالية زائد `1`، وأضفه إلى آخر الـ~queue~. وعندما يصبح الـ~queue~ فارغًا، اطبع قيمة `dist` للنهاية؛ وتبقى `-1` إذا لم يوجد مسار.

الـ~DFS~ يتبع مسارًا واحدًا إلى أعمق ما يستطيع، لذا قد يصل إلى النهاية عبر طريق ملتف، والخلايا التي علّمها تسد الطرق الأقصر. تجربة كل المسارات تجد الحد الأدنى، لكن عددها أسّي.

استخدم `collections.deque` في ~Python~، و`ArrayDeque` في ~Java~، و`std::queue` في ~C++~، و~array~ مع ~index~ يشير إلى أوله في ~JavaScript~. مع `2,500` خلية، يجتاز `list.pop(0)` و`shift()` الاختبارات أيضًا؛ ولا يستطيع نظام التصحيح رؤية أي ~queue~ استُخدم.

## الـ~complexity~
الـ~time complexity~: `O(r * c)`: كل خلية تدخل الـ~queue~ مرة واحدة على الأكثر.
الـ~space complexity~: `O(r * c)` من أجل `dist` والـ~queue~.

## انتبه إلى
- شبكة مفتوحة بحجم `1` في `1`. البداية هي النهاية، لذا الإجابة `0`.
- علّم الخلايا عند إضافتها، لا عند إخراجها.

اكتب الـ~queue~ وشبكة `dist` في المسألة أدناه.
TEXT,
    ],
    'prefix-count' => [
        'title' => 'عدّ الـ prefix',
        'body' => <<<'TEXT'
الـ~trie~ يخزن الكلمات على شكل مسارات: كل ~edge~ حرف، لذا فالمسار الذي يبدأ من الـ~root~ يكوّن ~prefix~. كل ~node~ يعدّ الكلمات التي تمر به، و~flag~ يحدد المكان الذي تنتهي فيه كلمة.

## مثال
أضف `app` و`apple`، مع عرض الأعداد:

    root
      a 2
        p 2
          p 2  end
            l 1
              e 1  end

الأمر `COUNT ap` يسير عبر `a` ثم `p`: `2`. والأمر `COUNT b` لا يجد ~edge~: `0`. وإضافة `apple` مرة أخرى تجد الـ~flag~ الخاص بها مضبوطًا: لا تغيير.

## طريقة التفكير في الحل
الأمر `ADD` ينشئ الـ~nodes~ الناقصة على امتداد الكلمة. إذا كان على آخرها ~flag~، فتوقف؛ وإلا فضع عليه الـ~flag~ وأضف `1` إلى كل عدد على المسار. الأمر `COUNT` يُرجع العدد الموجود في آخر ~node~ من الـ~prefix~. في ~Python~، يحمل `"#"` العدد ويحمل `"$"` الـ~flag~.

## ~Python~
    def add(word):
        node = root
        for ch in word:
            node = node.setdefault(ch, {"#": 0})
        if "$" in node:
            return
        node["$"] = True
        node = root
        for ch in word:
            node = node[ch]
            node["#"] += 1

## ~Java~
    static void add(String word) {
        Node node = root;
        for (char c : word.toCharArray())
            node = node.next.computeIfAbsent(c, k -> new Node());
        if (node.end) return;
        node.end = true;
        node = root;
        for (char c : word.toCharArray()) {
            node = node.next.get(c);
            node.count++;
        }
    }

## ~JavaScript~
    function add(word) {
      let node = root
      for (const c of word) node = node.next[c] ??= { next: {}, count: 0 }
      if (node.end) return
      node.end = true
      node = root
      for (const c of word) {
        node = node.next[c]
        node.count++
      }
    }

## ~C++~
    void add(const string& word) {
        Node* node = root;
        for (char c : word) {
            if (!node->next[c]) node->next[c] = new Node();
            node = node->next[c];
        }
        if (node->end) return;
        node->end = true;
        node = root;
        for (char c : word) {
            node = node->next[c];
            node->count++;
        }
    }

## الـ~complexity~
`O(length of the word)` لكل عملية، أي بحسب طول الكلمة، مهما كان عدد الكلمات المخزنة.

## انتبه إلى
- قراءة الـ~flag~ لا تعدّ إلا الكلمات التي تنتهي هناك: `COUNT ap` يعطي `0`، لا `2`.
- بدون فحص الـ~flag~، يجعل `ADD apple` الثاني نتيجة `COUNT app` تساوي `3`.
TEXT,
    ],
    'dictionary-with-wildcards' => [
        'title' => 'Dictionary with wildcards',
        'body' => <<<'TEXT'
الـ~trie~ نفسه، لكن دون أعداد. في الـ~pattern~، يتبع الحرف ~edge~ واحدًا، والنقطة تطابق حرفًا واحدًا بالضبط، أي حرف كان. لذا عند النقطة يجرّب البحث كل ~child~، بطريقة ~depth-first~، حتى يطابق أحدها.

## مثال
المخزّن: `bad` و`bed` و`dab`. `SEARCH .a.`:

    pattern  node  step
    .        b     first child
    a        ba    edge found
    .        bad   end flag: yes

`SEARCH b.` يصل إلى `ba` و`be`؛ ولا تنتهي كلمة عند أي منهما: `no`.

## طريقة التفكير في الحل
`search(node, p, i)` يسأل هل يطابق الـ~pattern~، من `i` فصاعدًا، ما يبدأ من هذا الـ~node~. عند نهاية الـ~pattern~ تكون الإجابة هي ~flag~ النهاية. في ~Python~، يحدد `"$"` النهاية.

## ~Python~
    def search(node, p, i):
        if i == len(p):
            return "$" in node
        if p[i] != ".":
            return p[i] in node and search(node[p[i]], p, i + 1)
        return any(search(kid, p, i + 1)
                   for ch, kid in node.items() if ch != "$")

## ~Java~
    static boolean search(Node node, String p, int i) {
        if (node == null) return false;
        if (i == p.length()) return node.end;
        char c = p.charAt(i);
        if (c != '.') return search(node.next.get(c), p, i + 1);
        for (Node kid : node.next.values())
            if (search(kid, p, i + 1)) return true;
        return false;
    }

## ~JavaScript~
    function search(node, p, i) {
      if (!node) return false
      if (i === p.length) return node.end
      if (p[i] !== '.') return search(node.next[p[i]], p, i + 1)
      return Object.values(node.next).some(kid => search(kid, p, i + 1))
    }

## ~C++~
    bool search(Node* node, const string& p, int i) {
        if (i == (int)p.size()) return node->end;
        if (p[i] != '.') return node->next.count(p[i]) && search(node->next[p[i]], p, i + 1);
        for (auto [ch, kid] : node->next)
            if (search(kid, p, i + 1)) return true;
        return false;
    }

## الـ~complexity~
بلا نقاط: `O(length)`. كل نقطة يمكن أن تتفرع إلى `26` اتجاهًا، لذا فإن `k` من النقاط قد تزور حتى `26^k` مسارًا، لكن عدد المسارات لا يتجاوز ما يسمح به حجم الـ~trie~؛ وإذا كان الـ~pattern~ كله نقاطًا فقد يمر البحث بمعظم الـ~trie~.

## انتبه إلى
- إذا كانت `apple` مخزنة، فإن `SEARCH app` ينتهي داخل `apple`: `no`.
- النقطة حرف واحد دائمًا، ولا تكون أبدًا بلا حرف: ست نقاط لا تطابق `apple`.
TEXT,
    ],
    'friend-groups' => [
        'title' => 'مجموعات الأصدقاء',
        'body' => <<<'TEXT'
الـ~union-find~ يتتبع مجموعات تندمج. كل شخص يشير إلى ~parent~؛ وتتبّع الـ~parents~ ينتهي عند قائد المجموعة. الدمج يجعل أحد القائدين يشير إلى الآخر.

## مثال
`JOIN 1 2`، و`JOIN 3 4`، و`JOIN 2 4`:

    person  1  2  3  4  5
    parent  1  1  1  3  5

`SAME 4 2`: كلاهما يصل إلى `1`، لذا `yes`.

## طريقة التفكير في الحل
`find` يسير إلى القائد، ويجعل كل شخص يمر به يشير إلى الـ~grandparent~ الخاص به (~path halving~). و`join` يعلّق المجموعة الأصغر تحت الأكبر (~union by size~).

## ~Python~
    def find(x):
        while parent[x] != x:
            parent[x] = parent[parent[x]]
            x = parent[x]
        return x

    def join(a, b):
        a, b = find(a), find(b)
        if a == b:
            return
        if size[a] < size[b]:
            a, b = b, a
        parent[b] = a
        size[a] += size[b]

## ~Java~
    static int find(int x) {
        while (parent[x] != x) {
            parent[x] = parent[parent[x]];
            x = parent[x];
        }
        return x;
    }

    static void join(int a, int b) {
        a = find(a);
        b = find(b);
        if (a == b) return;
        if (size[a] < size[b]) { int t = a; a = b; b = t; }
        parent[b] = a;
        size[a] += size[b];
    }

## ~JavaScript~
    function find(x) {
      while (parent[x] !== x) {
        parent[x] = parent[parent[x]]
        x = parent[x]
      }
      return x
    }

    function join(a, b) {
      a = find(a)
      b = find(b)
      if (a === b) return
      if (size[a] < size[b]) [a, b] = [b, a]
      parent[b] = a
      size[a] += size[b]
    }

## ~C++~
    int find(int x) {
        while (parent[x] != x) {
            parent[x] = parent[parent[x]];
            x = parent[x];
        }
        return x;
    }

    void join(int a, int b) {
        a = find(a);
        b = find(b);
        if (a == b) return;
        if (sz[a] < sz[b]) swap(a, b);
        parent[b] = a;
        sz[a] += sz[b];
    }

## الـ~complexity~
شبه ثابت لكل عملية. الاختبارات صغيرة، لذا فتخطي الـ~path halving~ سيجتاز الاختبارات، لكنه مهم عند ملايين العمليات.

## انتبه إلى
- قارن بين القادة، لا بين الأشخاص.
- بدون الـ~union by size~، إذا كان `join` ينفّذ دائمًا `parent[a] = b`، فإن الأوامر `JOIN 1 2`، `JOIN 2 3`، ... تبني مسارًا طوله `3000`؛ و`find` المكتوب بالـ~recursion~ يتجاوز الحد الافتراضي في ~Python~، وهو `1000` استدعاء.
TEXT,
    ],
    'minimum-spanning-tree' => [
        'title' => 'Minimum spanning tree',
        'body' => <<<'TEXT'
الـ~spanning tree~ يربط كل الـ~nodes~ وعددها `n` باستخدام `n - 1` من الـ~edges~، دون ~cycle~؛ والأصغر منها هو الذي مجموع أوزانه أقل ما يمكن. ~Kruskal's algorithm~ هو ~greedy~، مثل وحدة الـ~greedy~: رتّب الـ~edges~ حسب الوزن، وخذ كل ~edge~ يربط بين مجموعتين (بالـ~union-find~، من درس مجموعات الأصدقاء)، وتوقف عند `n - 1` من الـ~edges~.

## مثال
أربعة ~nodes~، والـ~edges~ مرتبة حسب الوزن:

    edge  weight  take?
    2-3   2       yes
    1-3   3       yes
    1-2   4       no, same group
    3-4   5       yes

المجموع: `2 + 3 + 5 = 10`.

## طريقة التفكير في الحل
أخف ~edge~ بين مجموعتين آمن دائمًا. والـ~edge~ داخل مجموعة واحدة، ومنه الـ~self-loops~، سيغلق ~cycle~. ومن الـ~parallel edges~ يأتي الأخف أولًا.

## ~Python~
    edges.sort()
    total = taken = 0
    for w, u, v in edges:
        if find(u) != find(v):
            join(u, v)
            total += w
            taken += 1
    print(total if taken == n - 1 else -1)

## ~Java~
    Arrays.sort(edges, (x, y) -> Integer.compare(x[2], y[2]));
    long total = 0;
    int taken = 0;
    for (int[] e : edges)
        if (find(e[0]) != find(e[1])) {
            join(e[0], e[1]);
            total += e[2];
            taken++;
        }
    System.out.println(taken == n - 1 ? total : -1);

## ~JavaScript~
    edges.sort((x, y) => x[2] - y[2])
    let total = 0, taken = 0
    for (const [u, v, w] of edges)
      if (find(u) !== find(v)) {
        join(u, v)
        total += w
        taken++
      }
    console.log(taken === n - 1 ? total : -1)

## ~C++~
    sort(edges.begin(), edges.end());
    long long total = 0;
    int taken = 0;
    for (auto [w, u, v] : edges)
        if (find(u) != find(v)) {
            join(u, v);
            total += w;
            taken++;
        }
    cout << (taken == n - 1 ? total : -1) << "\n";

## الـ~complexity~
`O(m log m)` من أجل الـ~sort~؛ وكل خطوة ~union-find~ شبه ثابتة.

## انتبه إلى
- بلا فحص للمجموعة: `2 + 3 + 4 = 9` في المثال أعلاه، وهذا ~cycle~ ينقصه الـ~node~ `4`.
- البدء بالأثقل يعطي الـ~maximum spanning tree~.
- `999` من الـ~edges~ أوزانها قريبة من `10^9` تتجاوز ~32-bit~: استخدم `long` (`long long` في ~C++~).
- إذا أُخذ أقل من `n - 1` من الـ~edges~: الـ~graph~ غير متصل، اطبع `-1`.
TEXT,
    ],
    'sort-an-array' => [
        'title' => 'رتّب الـ array',
        'body' => <<<'TEXT'
الـ~sorting~ يضع الأعداد بالترتيب، من الأصغر إلى الأكبر. الطرق الثلاث هنا هي نفسها الطرق الثلاث في قسم التمثيل المرئي في هذه الصفحة؛ شاهد كل واحدة منها هناك على ~array~ صغير قبل أن تكتب الكود.

الـ~bubble sort~ يمر على الـ~array~ ويبدّل كل عنصرين متجاورين ترتيبهما خاطئ. بعد كل ~pass~، يكون أكبر عدد متبقٍ قد وصل إلى مكانه النهائي في النهاية. والـ~pass~ الذي لا تبديل فيه يعني أن الـ~array~ مرتب.
الـ~insertion sort~ يأخذ كل عدد بدوره وينقله إلى اليسار، ويبدّله مع جيرانه الأكبر منه، حتى يصل إلى مكانه المناسب.
الـ~merge sort~ يقسم الـ~array~ إلى نصفين، ويرتب كل نصف، ثم يدمج النصفين المرتبين بأخذ العدد الأصغر من مقدمتيهما مرة بعد مرة.

## مثال
الـ~bubble sort~ على `3 -1 2`:
الـ~pass~ رقم `1`: قارن `3` و`-1`، وبدّلهما: `-1 3 2`. قارن `3` و`2`، وبدّلهما: `-1 2 3`.
الـ~pass~ رقم `2`: قارن `-1` و`2`، لا تبديل. لم يحدث أي تبديل، لذا الـ~array~ مرتب.
اطبع `-1 2 3`.

## طريقة التفكير في الحل
أي طريقة صحيحة تجتاز هذه المسألة، ومنها الـ~built-in sort~ في لغتك. ومع ذلك، اكتب واحدة من الطرق الثلاث بنفسك مرة واحدة على الأقل.

## الـ~complexity~
الـ~bubble sort~: وقت `O(n^2)`، لأنه قد يكون هناك `n - 1` من الـ~passes~، في كل منها حتى `n - 1` مقارنة؛ و`O(n)` على ~input~ مرتب إذا توقفت بعد ~pass~ بلا تبديل. مساحة إضافية `O(1)`.
الـ~insertion sort~: وقت `O(n^2)` على ~input~ معكوس، و`O(n)` على ~input~ مرتب. مساحة إضافية `O(1)`.
الـ~merge sort~: وقت `O(n log n)` على كل ~input~، لأن هناك نحو `log n` من مستويات التقسيم إلى نصفين، والدمج في كل مستوى يأخذ `O(n)`. مساحة إضافية `O(n)` من أجل الدمج.

## انتبه إلى
- `n = 0`. السطر الثاني فارغ. لا تجعل البرنامج يتعطل بسببه، واطبع سطرًا فارغًا.
- القيم المكررة والسالبة. ترتيب `0 -3 7 -3` يعطي `-3 -3 0 7`؛ احتفظ بنسختي `-3` كلتيهما.
- الـ~sort~ الافتراضي في ~JavaScript~ يقارن الأعداد على أنها نصوص: `[10, 9, -1].sort()` يعطي `[-1, 10, 9]`. مرّر ~comparator~: `sort((a, b) => a - b)`.

اكتب الـ~merge sort~ من أجل المسألة أدناه، وقارن الـ~output~ الخاص به بالـ~built-in sort~.
TEXT,
    ],
    'binary-search' => [
        'title' => 'Binary search',
        'body' => <<<'TEXT'
الـ~binary search~ يجد قيمة في ~array~ مرتب بأن يستبعد نصف النطاق المتبقي في كل خطوة. يعرض قسم التمثيل المرئي في هذه الصفحة كل خطوة مع تحديد `low` و`mid` و`high`؛ شاهده، ومنه حالة ~target~ غير موجود، قبل أن تكتب الكود.

## مثال
ابحث عن `9` في `-1 0 3 5 9 12`.
`low = 0`، `high = 5`، `mid = 2`: القيمة `a[2] = 3` أصغر من `9`، لذا `low = 3`.
`low = 3`، `high = 5`، `mid = 4`: `a[4] = 9`. وُجدت في الموضع `4`.
ابحث عن `2` في الـ~array~ نفسه.
`mid = 2`: `3` أكبر من `2`، لذا `high = 1`.
`mid = 0`: `-1` أصغر من `2`، لذا `low = 1`.
`mid = 1`: `0` أصغر من `2`، لذا `low = 2`.
الآن `low > high`. النطاق فارغ، لذا الإجابة `-1`.

## طريقة التفكير في الحل
`low` و`high` يحددان الجزء من الـ~array~ الذي ما زال الـ~target~ قد يكون فيه، والطرفان مشمولان. استمر ما دام `low <= high`. خذ `mid = low + (high - low) / 2`، مقرّبًا إلى الأسفل. إذا كان `a[mid]` هو الـ~target~، فقد انتهى البحث. وإذا كان أصغر، فالـ~target~ لا يمكن أن يكون إلا على اليمين، لذا `low = mid + 1`. وإذا كان أكبر، فإن `high = mid - 1`.

## الـ~complexity~
الـ~time complexity~: `O(log n)`، لأن النطاق ينقسم إلى النصف في كل خطوة؛ و`2000` عدد تحتاج إلى `11` فحصًا على الأكثر.
الـ~space complexity~: `O(1)`، لأن ما يُحفظ هو `low` و`high` و`mid` فقط.

## انتبه إلى
- `n = 0`. عندها يبدأ `high` من `-1`، ولا يعمل الـ~loop~ أبدًا، والإجابة `-1`.
- الـ~off-by-one~. استخدام `low = mid` بدلًا من `low = mid + 1` قد يجعل الـ~loop~ لا ينتهي أبدًا على نطاق من عنصرين، واستخدام `low < high` بدلًا من `low <= high` يتخطى آخر مرشح.
- ~target~ خارج نطاق القيم. البحث عن `100` في `1 3 5 7 9` ينقل `low` إلى ما بعد النهاية؛ ويوقفه الـ~loop condition~ هناك، والإجابة `-1`.

الـ~linear scan~ سيجتاز المسألة أدناه أيضًا، لذا حلّها بالتقسيم إلى نصفين عن قصد.
TEXT,
    ],
    'count-inversions' => [
        'title' => 'عدّ الـ inversions',
        'body' => <<<'TEXT'
الـ~inversion~ هو زوج من المواضع `i < j` حيث `a[i] > a[j]`: عدد أكبر يقف قبل عدد أصغر. الـ~array~ المرتب فيه `0` من الـ~inversions~، والـ~array~ المعكوس المكوّن من `n` من الأعداد المختلفة فيه `n(n-1)/2`.

## مثال
في `2 4 1 3 5` الـ~inversions~ هي `(2, 1)` و`(4, 1)` و`(4, 3)`، لذا العدد `3`.

## طريقة التفكير في الحل
الـ~brute force~: افحص كل زوج `i < j` وعُدّ الأزواج التي فيها `a[i] > a[j]`. عندما `n = 2000` يكون ذلك نحو `2` مليون فحص، وهذا يجتاز الاختبارات هنا.

الـ~merge sort~: عُدّ أثناء الدمج. قسّم `2 4 1 3 5` إلى `2 4` و`1 3 5`. كل نصف مرتب، وليس فيه أي ~inversion~ داخلي. الآن ادمج:
خذ `1` من النصف الأيمن. ما زال `2` و`4` ينتظران في النصف الأيسر وكلاهما أكبر، لذا أضف `2`.
خذ `2` من النصف الأيسر.
خذ `3` من النصف الأيمن. ما زال `4` ينتظر في اليسار، لذا أضف `1`.
خذ `4`، ثم `5`. لا شيء يُضاف.
المجموع: `0 + 0 + 3 = 3`.
كلما أخذت عددًا من النصف الأيمن، يكون كل عدد ما زال ينتظر في اليسار أكبر منه، لأن ذلك النصف مرتب، لذا أضف عدد الأعداد المنتظرة. الإجابة هي العدد من كل نصف زائد العدد من الدمج.

## الـ~complexity~
الـ~brute force~: وقت `O(n^2)`، لأن كل زوج يُفحص؛ ومساحة إضافية `O(1)`.
الـ~merge sort~: وقت `O(n log n)`، لأن العدّ يضيف `O(1)` إلى كل خطوة دمج؛ ومساحة إضافية `O(n)` من أجل الدمج.

## انتبه إلى
- القيم المتساوية ليست ~inversions~. في `3 3 1 -3 2 2` الإجابة `9`؛ الأزواج المتساوية لا تُحسب. عند الدمج، خذ من النصف الأيسر عند التعادل، وإلا حُسبت الأزواج المتساوية.
- `n = 0` و`n = 1` ليس فيهما أزواج، لذا الإجابة `0`.
- الحجم. عندما `n = 2000` قد يصل العدد إلى `1999000`، وهذا ما زال يتسع في `int` بحجم ~32-bit~.

ابدأ المسألة أدناه بالـ~brute force~، ثم استخدمه لاختبار نسخة الـ~merge sort~ على ~arrays~ عشوائية.
TEXT,
    ],
    'make-change' => [
        'title' => 'إعطاء الباقي',
        'body' => <<<'TEXT'
الـ~greedy algorithm~ يبني إجابته خطوة بخطوة: يأخذ الاختيار الذي يبدو الأفضل الآن ولا يتراجع عنه أبدًا. هذا سريع، لكنه لا يكون صحيحًا إلا عندما ينتمي الاختيار الذي يبدو الأفضل دائمًا إلى إحدى الإجابات المثلى.

في مسألة الباقي، الاختيار الـ~greedy~ هو أكبر عملة لا تتجاوز ما تبقى. مع عملات `1` و`5` و`10` و`25` و`100` سنت يكون ذلك هو الأمثل دائمًا، لذا فالمسألة أدناه تأخذ من كل عملة أكبر عدد ممكن، بدءًا من الأكبر.

## مثال
`99` سنتًا: `100` أكبر من المبلغ. `3 x 25` تترك `24`، و`2 x 10` تترك `4`، ولا شيء من `5`، ثم `4 x 1`. المجموع `3 + 2 + 4 = 9` عملات.

## طريقة التفكير في الحل
قد يفشل الـ~greedy~. مع العملات `4` و`3` و`1`، يحصل المبلغ `6` على `4 + 1 + 1`، أي `3` عملات، لكن `3 + 3` تحتاج إلى عملتين فقط؛ و`countCoins(6, [4, 3, 1])` يُرجع `3`. أنظمة العملات مثل هذا النظام تحتاج إلى ~dynamic programming~، وهو وحدة لاحقة. قبل أن تثق بالـ~greedy~، ابحث عن مثال مضاد.

## ~Python~
    def count_coins(amount, coins):  # largest coin first
        count = 0
        for c in coins:
            count += amount // c
            amount %= c
        return count

## ~Java~
    static long countCoins(long amount, long[] coins) {
        long count = 0;
        for (long c : coins) {
            count += amount / c;
            amount %= c;
        }
        return count;
    }

## ~JavaScript~
    function countCoins(amount, coins) {
      let count = 0
      for (const c of coins) {
        count += Math.floor(amount / c)
        amount %= c
      }
      return count
    }

## ~C++~
    long long countCoins(long long amount, const vector<long long>& coins) {
        long long count = 0;
        for (long long c : coins) {
            count += amount / c;
            amount %= c;
        }
        return count;
    }

## الـ~complexity~
`O(k)` لعدد `k` من أنواع العملات: عملية قسمة واحدة لكل عملة، مهما كان المبلغ كبيرًا.

## انتبه إلى
- المبلغ `10^12` سنت يحتاج إلى `10^10` عملة، وهذا أكثر مما يتسع له `int` بحجم ~32-bit~: استخدم `long` في ~Java~ و`long long` في ~C++~. الأعداد في ~JavaScript~ دقيقة حتى `2^53`.
- يجب أن تكون العملات مرتبة من الأكبر إلى الأصغر.
- `0` سنت يحتاج إلى `0` عملة.
TEXT,
    ],
    'maximum-meetings' => [
        'title' => 'أكبر عدد من الاجتماعات',
        'body' => <<<'TEXT'
لحضور أكبر عدد من الاجتماعات، رتّبها حسب وقت النهاية، وخذ كل اجتماع يبدأ عند نهاية آخر اجتماع أخذته أو بعدها. الاجتماع الذي ينتهي أولًا يترك أطول وقت للبقية، لذا فوضعه مكان غيره في أي خطة مثلى لا يجعل الخطة أسوأ أبدًا.

## مثال
`[1, 4) [3, 5) [0, 6) [5, 7) [8, 11) [12, 16)`، وهي مرتبة حسب وقت النهاية مسبقًا.
خذ `[1, 4)`؛ `end = 4`. الاجتماعان `[3, 5)` و`[0, 6)` يبدآن مبكرًا جدًا.
خذ `[5, 7)`؛ `end = 7`. خذ `[8, 11)`، ثم `[12, 16)`. النتيجة `4` اجتماعات.

## طريقة التفكير في الحل
قاعدتان من قواعد الـ~greedy~ تبدوان صحيحتين، لكنهما خاطئتان.
حسب وقت البداية: `[0, 10) [1, 2) [2, 3) [3, 4)` تأخذ `[0, 10)` أولًا وتحصل على `1`؛ والأفضل `3`.
حسب أقصر مدة: `[0, 5) [4, 6) [5, 10)` تأخذ `[4, 6)`، الذي يمنع الاجتماعين الآخرين، وتحصل على `1`؛ والأفضل `2`.

## ~Python~
    meetings.sort(key=lambda m: m[1])  # by end time
    count, end = 0, 0
    for s, e in meetings:
        if s >= end:
            count += 1
            end = e

## ~Java~
    Arrays.sort(meetings, (a, b) -> Integer.compare(a[1], b[1]));
    int count = 0, end = 0;
    for (int[] m : meetings)
        if (m[0] >= end) {
            count++;
            end = m[1];
        }

## ~JavaScript~
    meetings.sort((a, b) => a[1] - b[1])
    let count = 0, end = 0
    for (const [s, e] of meetings)
      if (s >= end) {
        count++
        end = e
      }

## ~C++~
    sort(meetings.begin(), meetings.end(), [](auto& a, auto& b) { return a[1] < b[1]; });
    int count = 0, end = 0;
    for (auto& [s, e] : meetings)
        if (s >= end) {
            count++;
            end = e;
        }

## الـ~complexity~
`O(n log n)` من أجل الـ~sort~، ثم ~pass~ واحد يأخذ `O(n)`.

## انتبه إلى
- الاجتماعات المتلامسة لا تتداخل: `[1, 3)` ينتهي عندما يبدأ `[3, 5)`، لذا يُحسب الاثنان. قارن باستخدام `>=`، لا `>`.
- رتّب حسب النهاية، لا حسب البداية.
TEXT,
    ],
    'connect-ropes' => [
        'title' => 'وصل الحبال',
        'body' => <<<'TEXT'
عملية الوصل تكلّف مجموع الطولين، والحبل الجديد يُوصل مرة أخرى لاحقًا، لذا يُدفع كل طول مرة واحدة مقابل كل عملية وصل فوقه. لذلك صِل دائمًا أقصر حبلين، باستخدام ~min-heap~. الـ~Huffman coding~ يستخدم الفكرة نفسها، فيدمج أندر رمزين أولًا.

## مثال
`4 3 2 6`. صِل `2 + 3 = 5`، والتكلفة `5`؛ الحبال `4 5 6`.
صِل `4 + 5 = 9`، والتكلفة `14`؛ الحبال `6 9`.
صِل `6 + 9 = 15`، والتكلفة `29`. ترتيب الـ~input~ كان سيكلّف `7 + 9 + 15 = 31`.

## طريقة التفكير في الحل
الـ~sorting~ مرة واحدة ثم الوصل بالتتابع يفشل أيضًا: `1 1 1 1` يكلّف `2 + 3 + 4 = 9` بهذه الطريقة، لكن الأفضل `2 + 2 + 4 = 8`. يجب أن يعود الحبل الموصول إلى بين الحبال الأخرى؛ والـ~heap~ يفعل ذلك.

## ~Python~
    heapq.heapify(ropes)
    total = 0
    while len(ropes) > 1:
        a = heapq.heappop(ropes)
        b = heapq.heappop(ropes)
        total += a + b
        heapq.heappush(ropes, a + b)

## ~Java~
    PriorityQueue<Long> h = new PriorityQueue<>();
    for (long x : ropes) h.add(x);
    long total = 0;
    while (h.size() > 1) {
        long a = h.poll(), b = h.poll();
        total += a + b;
        h.add(a + b);
    }

## ~JavaScript~
    const h = new Heap((x, y) => x < y)  // Last stone weight's class
    for (const x of ropes) h.push(x)
    let total = 0
    while (h.a.length > 1) {
      const a = h.pop(), b = h.pop()
      total += a + b
      h.push(a + b)
    }

## ~C++~
    priority_queue<long long, vector<long long>, greater<>> h(ropes.begin(), ropes.end());
    long long total = 0;
    while (h.size() > 1) {
        long long a = h.top();
        h.pop();
        long long b = h.top();
        h.pop();
        total += a + b;
        h.push(a + b);
    }

## الـ~complexity~
`n - 1` عملية وصل، كل منها `O(log n)`: أي `O(n log n)`.

## انتبه إلى
- ستة حبال طول كل منها `10^9` تكلّف `16 x 10^9`، وهذا يتجاوز ~32-bit~: استخدم `long` (`long long` في ~C++~) للمجموع وفي الـ~heap~.
- `n = 1` لا يحتاج إلى أي وصل: التكلفة `0`.
TEXT,
    ],
];
