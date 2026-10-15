<?php

// Lesson text (lessons.body), keyed by lesson slug. The frontend shows it as plain text:
// line breaks and indentation are kept, nothing else is formatted.
// The code snippets in warm-up were run through the sandbox in all four languages.

return [
    'warm-up' => <<<'TEXT'
Your program talks to the grader through two streams. The grader writes a test's input to standard input, runs your program, and reads what it prints to standard output. That text is compared with the expected answer. Whitespace at the start and end is ignored; everything else must match exactly.

## Example
The input is the line 21. Your program reads the text "21", converts it to the integer 21, doubles it to 42 and prints 42. The grader compares "42" with the expected "42" and marks the test passed.

## How to think about it
Every solution has three parts: read, compute, print. Input arrives as text, so convert it to numbers before doing arithmetic. Print only the answer. A prompt like "Enter a number:" becomes part of your output and fails the test.

Each snippet below reads one line of numbers and prints how many are even. Keep the reading and printing, and replace the middle.

## Python
    nums = list(map(int, input().split()))
    print(sum(1 for x in nums if x % 2 == 0))

## Java (the class must be named Main)
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

## JavaScript
    const input = require('fs').readFileSync(0, 'utf8');
    const nums = input.trim().split(/\s+/).map(Number);
    console.log(nums.filter((x) => x % 2 === 0).length);

## C++
    #include <iostream>
    using namespace std;

    int main() {
        int x, evens = 0;
        while (cin >> x) {
            if (x % 2 == 0) evens++;
        }
        cout << evens << endl;
    }

## Complexity
Time: O(1), because there is one number and one multiplication.
Space: O(1), because you keep a single value.

## Watch out for
- Negative numbers and zero: -7 doubled is -14.
- The largest inputs: 2 * 1000000000 = 2000000000 still fits in a 32-bit int, whose limit is 2147483647.
- Leftover debug prints. They are output too.

Try the read, compute, print cycle on the problem below, in the language you plan to use.
TEXT,

    'sum-of-an-array' => <<<'TEXT'
An array is a list of values stored one after another, each at a numbered position starting from 0. To add them up, keep a running total, the way a shop till adds each item as it is scanned.

## Example
The input is n = 5 and the numbers 10 -2 7 0 5.
Start with total = 0.
Add 10: total = 10.
Add -2: total = 8.
Add 7: total = 15.
Add 0: total = 15.
Add 5: total = 20.
Print 20.

## How to think about it
Read n first, then read exactly n numbers. Start the total at 0, not at the first number, so that an empty array works too. Add every number once. You don't need the numbers after adding them, so you can add each one as you read it instead of storing the whole array. This pattern, one variable updated during a single pass, comes back in many later problems.

## Complexity
Time: O(n), because each number is read and added once.
Space: O(1) extra if you add each number as you read it; O(n) if you store them all first. Both are fine at this size.

## Watch out for
- n = 0. The second line is empty and the answer is 0. In Java, "".split(" ") returns an array holding one empty string, and parsing it throws an exception. Reading exactly n numbers with a loop avoids that.
- Negative numbers. They lower the total. Nothing special is needed, but test with a few, such as -5 -5 3 3 -1 0, which sums to -5.
- Size. The largest possible total is 1000 * 1000000 = 1000000000, which fits in a 32-bit int. Problems with bigger limits need a 64-bit type: long in Java, long long in C++. Python integers never overflow, and JavaScript numbers are exact up to about 9 * 10^15.

Write the running total for the problem below, and check the n = 0 case before you submit.
TEXT,

    'maximum-of-an-array' => <<<'TEXT'
To find the largest number you only need to remember one thing as you go: the biggest value seen so far. Each new number either beats it or doesn't.

## Example
The input is 3 1 4 2.
Start with best = 3, the first number.
1 is not bigger than 3, so best stays 3.
4 is bigger than 3, so best becomes 4.
2 is not bigger than 4, so best stays 4.
Print 4.

## How to think about it
Take the first number as your starting best, then compare every other number with it once. Replace best only when a number is bigger. With equal values it makes no difference which one you keep, because you print the value, not the position. Like the sum, this is a single pass, so you can compare each number as you read it and never store the array. Sorting the array and taking the last element also gives the right answer, but it does far more work than needed.

## Complexity
Time: O(n), because each number is compared once.
Space: O(1) extra, because only best is kept (plus the input, if you store it).

## Watch out for
- All negative numbers. For -8 -3 -9 -3 -12 the answer is -3. If best starts at 0, you print 0, which isn't in the array at all. Start from the first element, or from the smallest possible value: Integer.MIN_VALUE in Java, INT_MIN in C++, -Infinity in JavaScript, float('-inf') in Python.
- A single number. With n = 1 there is nothing to compare, and that number is the answer.
- The range. Values go from -1000000000 to 1000000000. Both ends fit in a 32-bit int, so int is enough in Java and C++.
- Repeated maximum values. -3 appears twice in the first example; the answer is still just -3, printed once.

Solve the problem below in one pass, without sorting.
TEXT,

    'reverse-a-string' => <<<'TEXT'
A string is a sequence of characters, so you can treat it like an array. Reversing it puts the last character first and the first character last.

The two-pointer technique does this neatly. Put one pointer at the start and one at the end, swap the characters they point at, and move both pointers one step toward the middle. Stop when they meet or cross.

## Example
Take "abc def", 7 characters at positions 0 to 6.
Swap positions 0 and 6: "fbc dea".
Swap positions 1 and 5: "fec dba".
Swap positions 2 and 4: "fed cba".
Both pointers reach position 3, the space, so stop. The result is "fed cba".

## How to think about it
In Python, Java and JavaScript, strings can't be changed in place. Copy the characters into a list or array, swap there, and join them back into a string. Another way is to build a new string by reading the original from its last character to its first. Both give the same answer; the two-pointer version is worth writing once, because the next lessons reuse it.

## Complexity
Time: O(n), because each character is moved once.
Space: O(n) for the character array or the new string. Swapping inside a string you are allowed to change, such as a std::string in C++, needs only O(1) extra.

## Watch out for
- Spaces are part of the text. Read the whole line, not single words: in Java use nextLine(), not next(); in C++ use getline, not cin >> s. Spaces are reversed like any other character.
- An empty line. The answer is an empty line. Make sure your reading code accepts it instead of crashing or waiting for more input.
- Odd and even lengths. With left < right as the loop condition, both work, and the middle character of an odd-length string stays where it is.

Use the problem below to practise two pointers before the palindrome lesson, which uses the same idea.
TEXT,

    'two-sum' => <<<'TEXT'
Two Sum asks for two positions whose values add up to a target. The direct way checks every pair. The faster way remembers the values already seen, so each number needs only one lookup: whether its partner has appeared earlier.

## Example
Take 2 7 11 15 with target 9.
Position 0, value 2: the partner would be 9 - 2 = 7. Nothing has been seen yet, so remember 2 at position 0.
Position 1, value 7: the partner would be 9 - 7 = 2. 2 was seen at position 0, so the answer is 0 1.

## How to think about it
Walk through the array once with a hash map from value to position: a dict in Python, a HashMap in Java, a Map in JavaScript, an unordered_map in C++. For each value, compute target - value and look it up. If it's there, you have the pair, and the stored position comes first because it is earlier. If it isn't, store the current value and position and move on. Look up before you store, so a number never pairs with itself.

Two nested loops that try every pair also work. With n up to 2000 that is about 2 million pairs, which passes here.

## Complexity
Time: O(n) on average, because each value needs one lookup and one insert, and both take O(1) on average in a hash map. The nested loops take O(n^2).
Space: O(n) for the map. The nested loops need O(1).

## Watch out for
- Equal values. For 3 3 with target 6 the answer is 0 1. If you store a value before looking up its partner, the first 3 finds itself and you print 0 0.
- Negative numbers. For 4 -3 9 1 -5 with target -8, the pair is -3 and -5, at positions 1 and 4.
- Sorting. Sorting the array first loses the original positions, and those are what you print.

Write the hash map version for the problem below, and use the nested loops to check it on small inputs.
TEXT,

    'check-for-a-palindrome' => <<<'TEXT'
A palindrome reads the same in both directions: "level", "racecar", "abba". Checking one uses the two pointers from Reverse a string, but instead of swapping characters you compare them.

## Example
"abba": compare positions 0 and 3, a and a, equal. Compare positions 1 and 2, b and b, equal. The pointers have now crossed and every pair matched, so print yes.
"hello": compare positions 0 and 4, h and o. They differ, so stop right away and print no.

## How to think about it
Start with left at the first character and right at the last. While left < right, compare the two characters and move both pointers inward. On the first mismatch the answer is no. If the loop finishes, the answer is yes. You never look at a character twice, and a mismatch near the ends stops the work early.

Comparing the word with a reversed copy also works and is shorter to write. It costs memory for the copy and always reads the whole word.

## Complexity
Time: O(n), because at most n/2 pairs are compared.
Space: O(1) extra with two pointers; O(n) if you build a reversed copy.

## Watch out for
- One character. "a" is a palindrome. With left < right the loop doesn't run at all, and the answer is yes.
- Even and odd lengths. "abba" has no middle character and "racecar" has one. The same loop condition handles both, because a middle character only has to equal itself.
- A stray newline. In JavaScript, input read with readFileSync ends with a newline. Trim it, or the last character is "\n" and no word ever looks like a palindrome.
- Exact output. Print yes or no in lowercase, with no quotes or extra words.

The problem below is a direct use of this loop; check it on a long word as well as the short ones.
TEXT,

    'balanced-brackets' => <<<'TEXT'
A stack is a last-in, first-out collection: you push items onto the top and pop them off the top. Brackets fit this exactly, because the bracket opened most recently must be the first one closed.

## Example
"([]{})":
( is an opener: push. Stack: (
[ is an opener: push. Stack: ( [
] closes [, which is on top: pop. Stack: (
{ is an opener: push. Stack: ( {
} closes {, which is on top: pop. Stack: (
) closes (, which is on top: pop. The stack is empty.
The line is done and the stack is empty, so the answer is yes.
In "([)]", the third character ) finds [ on top. That is the wrong kind, so the answer is no.

## How to think about it
Push every opener. For every closer, the stack must not be empty and its top must be the matching opener; then pop and continue. Otherwise stop with no. When the line ends, the answer is yes only if the stack is empty. A small map from each closer to its opener keeps the check short. In Python a list works as a stack (append and pop); in Java use ArrayDeque, in JavaScript an array, in C++ std::stack or std::vector.

## Complexity
Time: O(n), because each character is pushed and popped at most once.
Space: O(n) in the worst case, when every character is an opener.

## Watch out for
- An empty line is balanced, so the answer is yes.
- Leftover openers. In "(" nothing goes wrong during the loop, so you must check that the stack is empty at the end.
- A closer on an empty stack. In ")(" the first character has nothing to match. Check for an empty stack before popping: popping an empty Python list or Java ArrayDeque throws an exception.
- Counting is not enough. "([)]" has one opener for every closer and is still wrong.

Solve the problem below with a stack rather than counters.
TEXT,

    'evaluate-a-postfix-expression' => <<<'TEXT'
In postfix notation, also called reverse Polish notation, each operator comes after its two operands. "3 4 +" means 3 + 4, and "3 4 + 2 *" means (3 + 4) * 2. The order of the tokens already fixes the order of operations, so no parentheses are needed, and a stack evaluates the expression in one pass.

## Example
"3 4 + 2 *":
3 is a number: push. Stack: 3
4 is a number: push. Stack: 3 4
+ pops 4, then 3, and pushes 3 + 4. Stack: 7
2 is a number: push. Stack: 7 2
* pops 2, then 7, and pushes 7 * 2. Stack: 14
No tokens are left. The one value on the stack, 14, is the answer.

## How to think about it
Split the line on spaces. Push each number. For an operator, pop twice: the first pop is the right operand and the second is the left. Compute left operator right and push the result. A valid expression leaves exactly one value on the stack at the end.

## Complexity
Time: O(n) for n tokens, because each token is handled once and each push or pop takes O(1).
Space: O(n) for the stack in the worst case, when many numbers come before the first operator.

## Watch out for
- Operand order. "2 7 -" is 2 - 7 = -5, not 7 - 2. Division has the same trap.
- Division truncates toward zero. "0 7 - 2 /" is -7 / 2 = -3. Integer division in Java and C++ already truncates. In Python, // rounds down and gives -4, so use int(a / b). In JavaScript, use Math.trunc(a / b).
- A single number. "42" has no operators, and the answer is 42.
- Negative results. The input numbers are never negative, but intermediate results can be, as in "2 7 -".

Build the stack loop for the problem below, and test the operand-order case first.
TEXT,

    'next-greater-element' => <<<'TEXT'
For each number, the next greater element is the first number to its right that is strictly larger, or -1 if there is none. Scanning to the right from every position works but repeats a lot of work. A stack of positions that are still waiting for an answer removes the repetition.

## Example
Take 4 5 2 25.
Position 0, value 4: the stack is empty. Push 0.
Position 1, value 5: 5 > 4, so the answer for position 0 is 5. Pop it, then push 1.
Position 2, value 2: 2 is not greater than 5. Push 2.
Position 3, value 25: 25 > 2, so position 2 gets 25. 25 > 5, so position 1 gets 25. Push 3.
The end: position 3 is still waiting, so its answer stays -1.
Print 5 25 25 -1.

## How to think about it
Fill an answer array with -1. Walk from left to right. While the stack is not empty and the current value is greater than the value at the top position, the top position has found its answer: write it and pop. Then push the current position. The values at the stacked positions never increase from bottom to top, so the top is always the next one to be answered.

The brute force, scanning right from every position, takes O(n^2). With n up to 2000 it also passes here.

## Complexity
Time: O(n), because each position is pushed once and popped at most once.
Space: O(n) for the stack and the answer array.

## Watch out for
- Equal values. Strictly greater means a 2 does not answer an earlier 2. For 2 2 3 3 1 the answer is 3 3 -1 -1 -1. Compare with >, not >=.
- Store positions, not values. You need the position to know where to write each answer.
- A decreasing array. For -1 -2 -3 -4 nothing is ever popped, and every answer is -1.

Write the stack version for the problem below, and compare it with the brute force on small inputs.
TEXT,

    'reverse-a-linked-list' => <<<'TEXT'
A linked list stores its values in nodes. Each node holds a value and next, a pointer to the following node. The last node's next is nothing: None in Python, null in Java and JavaScript, nullptr in C++. The list is a pointer to its first node, the head. To visit every value, start at the head and follow next until you reach nothing.

Unlike an array, a linked list has no index to jump to, but reordering it only means changing next pointers.

## Example
Take 1 -> 2 -> 3, with prev at nothing and cur at the head.
cur = 1: save its next, 2. Point 1 at prev, nothing. Then prev = 1 and cur = 2.
cur = 2: save 3. Point 2 at 1. Then prev = 2 and cur = 3.
cur = 3: save nothing. Point 3 at 2. Then prev = 3 and cur = nothing.
cur is nothing, so stop. prev is the new head: 3 -> 2 -> 1.

## How to think about it
First build the list. A node is a small class with val and next in Python and Java, an object { val, next } in JavaScript, and a struct in C++. Keep a tail pointer, so each new node is attached in O(1).

Then walk once with prev and cur. At each node, save cur.next, point cur.next back at prev, and step both pointers forward. When cur runs out, prev is the new head.

## Complexity
Time: O(n), because each node is visited once to build, once to reverse, once to print.
Space: O(n) for the nodes; the reversal itself needs O(1).

## Watch out for
- Losing the rest of the list. Set cur.next = prev before saving the old cur.next, and the remaining nodes are lost.
- Returning the wrong head. After the loop, the new head is prev, not cur.
- n = 0. The loop never runs, and the answer is an empty line.

Printing the input backwards also passes the problem below, so turn the pointers around on purpose.
TEXT,

    'middle-of-a-linked-list' => <<<'TEXT'
Each node holds a value and next, the pointer to the following node; the last node's next is nothing. A linked list doesn't know its length, so you can't jump to the middle. Counting takes two passes: walk once to count n, then take n / 2 steps, rounded down. Two pointers take one pass: slow moves one node at a time and fast moves two. When fast reaches the end, slow has covered half the distance.

## Example
Take 1 2 3 4 5. Both pointers start at the head, 1.
Step 1: slow moves to 2, fast moves to 3.
Step 2: slow moves to 3, fast moves to 5.
fast.next is nothing, so stop. slow is at 3, the middle.
Take 1 2 3 4 5 6.
Step 1: slow 2, fast 3. Step 2: slow 3, fast 5.
Step 3: slow moves to 4, and fast moves past 6 to nothing.
fast is nothing, so stop. slow is at 4, the second of the two middles, 3 and 4.

## How to think about it
Loop while fast and fast.next are both not nothing, moving slow one step and fast two. The first check ends the loop for an even n, the second for an odd n. Slow then lands on the second middle when n is even, as the problem asks.

## Complexity
Time: O(n), because fast crosses the list once.
Space: O(1) extra for the two pointers, plus O(n) for the nodes.

## Watch out for
- The loop condition. Checking only fast crashes on an odd length, when the last node's next.next is read. Checking only fast.next crashes on an even length, once fast is nothing.
- Which middle. Stopping when fast.next.next is nothing gives the first middle: 3 instead of 4 for 1 2 3 4 5 6, and 10 instead of 20 for 10 20.
- n = 1. The loop never runs, and the head is the answer.

The fast and slow pointers come back in the next lesson, so write them here instead of counting first.
TEXT,

    'detect-a-cycle' => <<<'TEXT'
A normal list ends in nothing. If the last node points back to an earlier node, the walk never ends: that loop is a cycle.

A set of visited nodes works but needs O(n) extra memory. Floyd's method uses the slow and fast pointers from Middle of a linked list instead. Without a cycle, fast reaches the end. With one, both end up in the loop and fast gains one node per step, so it lands on slow.

## Example
Take 3 2 0 -4 with pos 1: the -4 node links back to the 2 node.
Both start at 3.
Step 1: slow moves to 2, fast moves to 0.
Step 2: slow moves to 0, fast moves to -4 and on to 2.
Step 3: slow moves to -4, fast to 0 and on to -4. Same node, so print yes.
Take 1 2 1 2 with pos -1. In step 2, fast moves past the last node to nothing, so print no.

## How to think about it
Create the nodes in an array, link each to the next, and if pos is not -1, point the last node at node pos. Start slow and fast at the head. While fast and fast.next are not nothing, move both, then check whether they are the same node. If the loop ends, there is no cycle.

## Complexity
Time: O(n). Slow enters the loop within n steps, and fast catches it within one more lap.
Space: O(1) extra; O(n) with a visited set.

## Watch out for
- Compare nodes, not values. In 1 2 1 2 the values repeat without a cycle. Use is in Python, == on objects in Java, === in JavaScript, pointer equality in C++.
- Compare after moving: both start at the head, so checking first always says yes.
- A node linking to itself. With n = 1 and pos = 0, the answer is yes.
- n = 0. With no nodes, the answer is no.

Answering from pos alone would pass the problem below; write the fast and slow pointers anyway.
TEXT,

    'sort-an-array' => <<<'TEXT'
Sorting puts numbers in order, smallest first. The three methods here are the same three as in the visualization section on this page; watch each one there on a small array before you code it.

Bubble sort walks through the array and swaps neighbours that are out of order. After each pass, the largest remaining number has reached its final place at the end. A pass with no swaps means the array is sorted.
Insertion sort takes each number in turn and moves it left, swapping it with bigger neighbours, until it fits.
Merge sort splits the array in half, sorts each half, then merges the two sorted halves by repeatedly taking the smaller front number.

## Example
Bubble sort on 3 -1 2:
Pass 1: compare 3 and -1, swap: -1 3 2. Compare 3 and 2, swap: -1 2 3.
Pass 2: compare -1 and 2, no swap. No swaps, so the array is sorted.
Print -1 2 3.

## How to think about it
Any correct method passes this problem, including your language's built-in sort. Still, write one of the three yourself at least once.

## Complexity
Bubble sort: O(n^2) time, because there can be n - 1 passes of up to n - 1 comparisons; O(n) on sorted input if you stop after a pass with no swaps. O(1) extra space.
Insertion sort: O(n^2) time on reversed input, O(n) on sorted input. O(1) extra space.
Merge sort: O(n log n) time on every input, because there are about log n levels of halving and merging each level takes O(n). O(n) extra space for merging.

## Watch out for
- n = 0. The second line is empty. Don't crash on it, and print an empty line.
- Duplicates and negatives. 0 -3 7 -3 sorts to -3 -3 0 7; keep both copies of -3.
- JavaScript's default sort compares numbers as text: [10, 9, -1].sort() gives [-1, 10, 9]. Pass a comparator: sort((a, b) => a - b).

Write merge sort for the problem below, and check its output against the built-in sort.
TEXT,

    'binary-search' => <<<'TEXT'
Binary search finds a value in a sorted array by throwing away half of the remaining range at every step. The visualization section on this page shows each step with low, mid and high marked; watch it, including a missing target, before you code it.

## Example
Search for 9 in -1 0 3 5 9 12.
low = 0, high = 5, mid = 2: a[2] = 3 is smaller than 9, so low = 3.
low = 3, high = 5, mid = 4: a[4] = 9. Found at position 4.
Search for 2 in the same array.
mid = 2: 3 is bigger than 2, so high = 1.
mid = 0: -1 is smaller than 2, so low = 1.
mid = 1: 0 is smaller than 2, so low = 2.
Now low > high. The range is empty, so the answer is -1.

## How to think about it
low and high mark the part of the array where the target can still be, both ends included. Keep going while low <= high. Take mid = low + (high - low) / 2, rounded down. If a[mid] is the target, you're done. If it's smaller, the target can only be to the right, so low = mid + 1. If it's bigger, high = mid - 1.

## Complexity
Time: O(log n), because the range halves at every step; 2000 numbers need at most 11 checks.
Space: O(1), because you only keep low, high and mid.

## Watch out for
- n = 0. Then high starts at -1, the loop never runs, and the answer is -1.
- Off-by-one. low = mid instead of low = mid + 1 can loop forever on a range of two, and low < high instead of low <= high skips the last candidate.
- Targets outside the values. Searching for 100 in 1 3 5 7 9 moves low past the end; the loop condition stops it there, and the answer is -1.

A linear scan would also pass the problem below, so solve it by halving on purpose.
TEXT,

    'count-inversions' => <<<'TEXT'
An inversion is a pair of positions i < j where a[i] > a[j]: a bigger number standing before a smaller one. A sorted array has 0 inversions, and a reversed array of n different numbers has n(n-1)/2.

## Example
In 2 4 1 3 5 the inversions are (2, 1), (4, 1) and (4, 3), so the count is 3.

## How to think about it
Brute force: check every pair i < j and count those with a[i] > a[j]. For n = 2000 that is about 2 million checks, which passes here.

Merge sort: count while you merge. Split 2 4 1 3 5 into 2 4 and 1 3 5. Each half is sorted, with 0 inversions inside. Now merge:
Take 1 from the right half. 2 and 4 are still waiting in the left half and both are bigger, so add 2.
Take 2 from the left half.
Take 3 from the right half. 4 is still waiting on the left, so add 1.
Take 4, then 5. Nothing to add.
Total: 0 + 0 + 3 = 3.
Whenever you take a number from the right half, every number still waiting on the left is bigger, because that half is sorted, so add how many are waiting. The answer is the count from each half plus the count from the merge.

## Complexity
Brute force: O(n^2) time, because every pair is checked; O(1) extra space.
Merge sort: O(n log n) time, because counting adds O(1) to each merge step; O(n) extra space for merging.

## Watch out for
- Equal values are not inversions. For 3 3 1 -3 2 2 the answer is 9; equal pairs don't count. When merging, take from the left half on a tie, or equal pairs get counted.
- n = 0 and n = 1 have no pairs, so the answer is 0.
- Size. For n = 2000 the count can reach 1999000, which still fits in a 32-bit int.

Start the problem below with the brute force, then use it to test a merge sort version on random arrays.
TEXT,
];
