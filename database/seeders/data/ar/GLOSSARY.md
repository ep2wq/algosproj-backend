# Arabic glossary

For the Arabic course text in this folder (`modules.php`, `lessons.php`, `problems.php`). The
translation rules (what is translated, what stays English, the `~term~` marker and the "الـ"
rule) are in the backend README, "Content languages". Load this list before translating and add
every new entry afterwards. ⚠ marks a choice to review.

Translated so far: Getting started (`basics`), Programming basics, Core programming,
Object-oriented programming, Algorithm basics, Arrays and strings, Stacks and queues, Linked
lists, Hash tables, Trees and BSTs, Heaps and priority queues, Recursion and backtracking,
Graphs, Tries and union-find, Sorting and searching.

## Part 1: terms kept in English

Written as `~term~` in prose (`الـ~term~` for the definite form), never with an Arabic
equivalent. Code, identifiers, keywords, values and Big-O stay in `inline code`. An English term
is treated as masculine (الـ~array~ الفارغ); language names are feminine (لغة): ~Python~ تعطي.
Plurals are English inside the marker (~strings~), never an Arabic suffix. "للـ" is not used:
write "من أجل الـ~stack~" or "الخاص بالـ~successor~" instead.

- Data and structures: array, character array, 2D list, list, lists, sorted list, string,
  character, index, indexes, element ⚠ (written عنصر, see Part 2), stack, top, map, hash map,
  dictionary, key, keys, entry, entries, lookup table, newline, whitespace, printable ASCII
  characters, queue, linked list, singly linked list, node, nodes, head, tail, cycle, set, hash
  table, hash function, substring, subarray, subarrays, prefix, prefix sum, tree, trees, binary
  tree, binary search tree, BST, root, child, children, parent, subtree, subtrees, leaf, edges,
  ancestor, lowest common ancestor, LCA, successor, heap, max-heap, min-heap, iterator, token,
  tokens, iterators, leaves, binary string, call tree, call stack, sets, graph, undirected,
  undirected graph, edge, adjacency list, connected component, connected components,
  component, components, visited set, self-loops, parallel edges, weighted edges, offsets,
  trie, flag, pattern, patterns, prefixes, parents, grandparent, spanning tree, spanning trees,
  minimum spanning tree, maximum spanning tree, arrays.
- Programming: variable, type, function, built-in function, parameter, return value, loop, for
  loop, nested loops, loop condition, conditions, counter, recursion, exception, overflow,
  undefined, debugging, compilation, regular expression, remainder operator, integer division,
  in place, immutable, builder, input, inputs, output, standard input, standard output,
  streams, 32-bit, 64-bit, 32-bit integer, 32-bit signed integer, signed 64-bit integer,
  operator, operators, operand, operands, counters, recursive function, recursion limit,
  parent pointers, pointer equality, trim, sandbox ⚠ ("في الـ~sandbox~ لدينا" = in our sandbox),
  base case, locale ⚠ ("الـ~locale~ الخاص بالكمبيوتر" = the computer's locale), built-in sort.
- Objects: class, classes, object, field, fields, private fields, private, static field,
  class-level field, method, methods, constructor, getter, encapsulation, shared state,
  subclass, subclasses, base class, interface, abstract class, abstract methods, pure virtual
  method, body, override, polymorphism.
- Algorithms: complexity, time complexity, space complexity, single pass, running total,
  sorting, sort, stable, stable sort, comparator, two passes, lookup, insert, linear search,
  binary search, two pointers, two-pointer technique, pointer, pointers, target ⚠, palindrome,
  trial division, off-by-one, factorial ⚠, Euclid's algorithm ⚠, Sieve of Eratosthenes ⚠,
  last-in, first-out, push, pop, peek, sift up, sift down, postfix notation, postfix
  expression, reverse Polish notation, next greater element, brute force, fast and slow
  pointers, Floyd ⚠ (name: "طريقة ~Floyd~"), sliding window, window, anagram, anagrams,
  preorder, preorder traversal, comparators, backtracking, backtrack, pruning, permutation ⚠,
  permutations, lexicographic order ⚠, N-Queens (in prose), walk ⚠, walks, depth-first,
  depth-first search, breadth-first search, DFS, BFS, recursive DFS, union-find, path halving,
  union by size, compression ⚠, Kruskal's algorithm ⚠, greedy, bubble sort, insertion sort,
  merge sort, pass ⚠ ("الـ~pass~ رقم `1`" = pass 1), passes, linear scan, inversion, inversions.
- Language names: Python, Java, JavaScript, C++.
- Problem section labels: ~Input~, ~Output~, ~Constraints~ ⚠ (the interface says المدخلات,
  المخرجات; القيود was used before).
- Titles that stay English: FizzBuzz, Factorial ⚠, Caesar cipher ⚠, Stack class, Linear search,
  Two Sum, Object-oriented programming, Arrays and strings, Stacks and queues, Linked lists,
  Hash tables, Trees and BSTs, Heaps and priority queues, Next greater element, Valid anagram,
  Longest substring without repeating characters, Subarray sum equals K, Lowest common ancestor
  of a BST ⚠, Last stone weight ⚠, Merge k sorted lists, Running median ⚠, Recursion and
  backtracking, Binary strings, Permutations ⚠, N-Queens, Graphs, Number of islands ⚠, Tries and
  union-find, Dictionary with wildcards, Minimum spanning tree, Binary search. A title with an
  English term and Arabic writes "الـ " and the term with a space, no marker: "مجموع عناصر الـ
  array", "عكس الـ linked list", "ارتفاع الـ BST", "حساب قيمة الـ postfix expression", "عدّ الـ
  connected components", "عدّ الـ prefix" ⚠, "رتّب الـ array", "عدّ الـ inversions", "الـ sorting
  والبحث" ⚠ (module). Translated without a term: "أقصر مسار في الشبكة", "مجموعات الأصدقاء".

## Part 2: Arabic wording for recurring words and phrases

| English | Arabic | Note |
|---|---|---|
| Getting started (module) | البداية | |
| Programming basics (module) | أساسيات البرمجة | |
| Core programming (module) | مهارات البرمجة الأساسية | ⚠ "Core programming skills" in back-translation |
| Algorithm basics (module) | أساسيات الخوارزميات | |
| Warm-up | الإحماء | |
| ## Example | ## مثال | |
| ## How to think about it | ## طريقة التفكير في الحل | not gendered (no "تفكر") |
| ## Complexity | ## الـ~complexity~ | ⚠ |
| Time: / Space: | الـ~time complexity~: / الـ~space complexity~: | |
| ## Watch out for | ## انتبه إلى | |
| Read ... (problem) | اقرأ | |
| Print ... | اطبع | |
| return (verb) / it returns | أرجِع / يُرجع | |
| One line with X. | سطر واحد فيه X. | |
| Line 1: n, the number of elements. | السطر 1: `n`، عدد العناصر. | |
| Next n lines: | الأسطر التالية وعددها `n`: | no "الـ" before code |
| the n integers | الأعداد الصحيحة وعددها `n` | |
| separated by single spaces | تفصل بينها مسافة واحدة | |
| a <= each value <= b | كل قيمة بين `a` و`b`، شاملًا الطرفين | a single expression stays in code |
| 0 <= length of s <= 1000 | طول `s` بين `0` و`1000`، شاملًا الطرفين | |
| 0-based | مع العدّ من `0` | |
| in lower case | بأحرف صغيرة | ⚠ |
| the grader | نظام التصحيح | ⚠ |
| The grader only sees ... | نظام التصحيح لا يرى إلا ... | |
| ... also passes | ... يجتاز الاختبارات أيضًا | |
| the problem below | المسألة أدناه | |
| test (test case) | اختبار | as the interface |
| expected answer | الإجابة المتوقعة | |
| In Java and C++, the lines after // in main: go inside main. | في ~Java~ و~C++~، توضع الأسطر التي بعد `// in main:` داخل `main`. | |
| The program reads ... from standard input, and your classes do the work | يقرأ البرنامج ... من الـ~standard input~، والـ~classes~ التي تكتبها تؤدي العمل | |
| on average | في المتوسط | |
| code | كود | as the interface |
| snippet | مقتطف كود | |
| the computer | الكمبيوتر | ⚠ everyday word; الحاسوب also understood |
| integer / whole number | عدد صحيح | ⚠ school-math word kept Arabic |
| prime / divisor / remainder / multiple | عدد أولي / قاسم / باقي القسمة / مضاعف | school math in Arabic |
| main / anti-diagonal | القطر الرئيسي / القطر الثانوي | ⚠ |
| greatest common divisor | القاسم المشترك الأكبر | ⚠ title translated |
| vowels | حروف العلة | ⚠ with the letters listed |
| case of a letter | حالة الحرف | ⚠ |
| element | عنصر | ⚠ kept Arabic, very common |
| position | موضع | index stays ~index~ |
| score (leaderboard) | الدرجة | ⚠ the interface says نقاط for points |
| sort (verb) | رتّب | ⚠ the noun stays ~sorting~ |
| swap | بدّل | |
| descending order / alphabetically | الترتيب التنازلي / أبجديًا | |
| wraps around (numbers) | تلتف | ⚠ |
| grid | الشبكة | |
| balance / deposit / withdraw | الرصيد / إيداع / اسحب | |
| push X / pop X (in worked steps) | نفّذ ~push~ للقيمة `X` / نفّذ ~pop~ للقيمة `X` | ⚠ the operation stays a term |
| bracket / opener / closer / balanced | قوس / قوس فتح / قوس إغلاق / متوازن | ⚠ |
| strictly greater | أكبر تمامًا | ⚠ |
| rounds down / truncates toward zero | يقرّب إلى الأسفل / يقرّب نحو الصفر | |
| nothing (a pointer to no node) | لا شيء | ⚠ |
| loop (the ring a cycle makes) | الحلقة | ⚠ the ~cycle~ itself stays English |
| insert (into a BST) / insertion order | أدخل / ترتيب الإدخال | ⚠ the hash-map operation stays ~insert~ |
| Insert the n values one by one, in the order given, into an empty binary search tree | أدخل القيم وعددها `n` واحدة تلو الأخرى، بالترتيب المعطى، في ~binary search tree~ فارغ | |
| Never rebalance the tree. | لا تُعِد توازن الـ~tree~ أبدًا. | ⚠ |
| height / depth / level / path / chain | الارتفاع / العمق / مستوى / مسار / سلسلة | ⚠ |
| split point | نقطة الانفصال | ⚠ |
| link (in a list or tree) | رابط | ⚠ |
| All values are distinct. | كل القيم مختلفة. | |
| k between a and b (a single variable) | `k` بين `a` و`b`، شاملًا الطرفين | |
| contiguous / non-decreasing order | متصلة / ترتيب غير تنازلي | |
| median / average | الوسيط / المتوسط | ⚠ |
| beats (heap order) | يتفوق على | ⚠ |
| it crashes | يجعل البرنامج يتعطل | ⚠ |
| count of a letter | عدد مرات الحرف | |
| visited nodes | الـ~nodes~ التي تمت زيارتها | |
| a lesson named in prose | درس + its Arabic title, terms as markers: "درس منتصف الـ~linked list~" | |
| choose, explore, undo | اختر، استكشف، تراجع | ⚠ undo = تراجع (عن الاختيار) |
| a call / calls itself / nested calls | استدعاء / يستدعي الـ~function~ نفسه / استدعاء متداخل | |
| end the call (return in a base case) | أنهِ الاستدعاء | |
| leading zeros | الأصفار في البداية | ⚠ |
| in binary, n digits | بالنظام الثنائي، بعدد `n` من الخانات | |
| increasing / ascending order | ترتيب تصاعدي | |
| queen / board / row / column / diagonal | ملكة / رقعة / صف / عمود / قطر | ⚠ رقعة |
| attacked / dead end | مهاجَم / طريق مسدود | ⚠ |
| neighbour | جار / جيران | ⚠ |
| cell / wall / open cell / island | خلية / جدار / خلية مفتوحة / جزيرة | |
| ring (BFS) | حلقة | ⚠ same word as the ring of a ~cycle~ |
| detour / exponentially many | طريق ملتف / عددها أسّي | ⚠ |
| front / back of the queue | مقدمة الـ~queue~ / آخر الـ~queue~ | "النهاية" is kept for the end cell |
| word / command | كلمة / أمر | |
| a dot (in a pattern) | النقطة / الرمز `.` | |
| leader (union-find) | قائد | ⚠ |
| weight / total weight | الوزن / مجموع الأوزان | |
| visualization section | قسم التمثيل المرئي | as the interface |
| X: O(...) time, O(...) extra space (per method) | وقت `O(...)` / مساحة إضافية `O(...)` | |
| O(length of the word) | `O(length of the word)`، أي بحسب طول الكلمة | ⚠ Big-O kept in code |
| reversed / sorted input | ~input~ معكوس / ~input~ مرتب | |
