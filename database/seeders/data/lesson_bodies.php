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

    'even-or-odd' => <<<'TEXT'
Input arrives as text, so first turn it into a number. In Python that is int(input()). In Java, use a Scanner and nextInt(). In C++, cin >> n. In JavaScript, read the line and call Number on it.

A number is even when it splits into two equal whole halves. The remainder operator, %, gives what is left over after a division: 7 % 2 is 1, because 7 = 3 * 2 + 1. So n is even exactly when n % 2 is 0.

## Example
n = 7: 7 % 2 is 1, not 0, so print odd.
n = 0: 0 % 2 is 0, so print even. Zero is even.
n = -3: here the languages disagree. Python gives -3 % 2 = 1. Java, C++ and JavaScript give -1.

## How to think about it
The difference comes from how each language rounds the division. Python rounds -3 / 2 down, to -2, so the remainder is 1: -3 = -2 * 2 + 1. Java, C++ and JavaScript round toward zero, to -1, so the remainder is -1: -3 = -1 * 2 - 1.

Both rules agree on one thing: an even number always leaves 0. So test n % 2 == 0 for even, and everything else is odd. That works for positive numbers, negative numbers and zero, in every language.

## Complexity
Time: O(1), one division.
Space: O(1).

## Watch out for
- Testing n % 2 == 1 for odd. In Java, C++ and JavaScript, -3 % 2 is -1, so -3 comes out even. In Python the same test happens to work.
- The size of n. 1,000,000,000 fits in a 32-bit int, whose largest value is 2,147,483,647, so int is enough here.
- The exact words: even and odd, in lower case.

The grader only sees the word, so it can't tell whether you used % or some other trick. Use %: it comes back in nearly every problem.
TEXT,

    'fizzbuzz' => <<<'TEXT'
A loop repeats a block of code. A for loop runs it once for each value of a counter. In Python, for i in range(1, n + 1) gives i = 1, 2, ..., n. In Java, C++ and JavaScript, for (i = 1; i <= n; i++) does the same.

A number is a multiple of 3 when dividing by 3 leaves nothing over: i % 3 == 0. The same goes for 5.

## Example
Take n = 15. The lines are:
1, 2, Fizz, 4, Buzz, Fizz, 7, 8, Fizz, Buzz, 11, Fizz, 13, 14, FizzBuzz.
3, 6, 9 and 12 are multiples of 3. 5 and 10 are multiples of 5. 15 is a multiple of both, so it gets FizzBuzz.

## How to think about it
Inside the loop, use an if, else if, else chain. The computer checks the conditions from the top and runs only the first one that is true.

That is why the FizzBuzz case goes first. A number that is a multiple of both 3 and 5 is also a multiple of 3. If the test for 3 comes first, 15 matches it, prints Fizz, and the FizzBuzz line is never reached. So test i % 15 == 0 first: a multiple of both 3 and 5 is exactly a multiple of 15. Then test 3, then 5, and otherwise print i.

Another way works too: start with an empty word, add Fizz for a multiple of 3, add Buzz for a multiple of 5, and print i if the word is still empty.

## Complexity
Time: O(n), one pass through the numbers.
Space: O(1) besides the output.

## Watch out for
- The range. Start at 1 and include n itself: range(1, n) stops at n - 1.
- One answer per line, spelled exactly Fizz, Buzz and FizzBuzz.
- n = 1. The only line is 1.

The grader only compares the lines, so it can't tell which of the two ways you used. Try n = 15 first and check the last line says FizzBuzz.
TEXT,

    'count-vowels' => <<<'TEXT'
A string is a piece of text, and a character is one letter, digit or space in it. Here the input is a whole line, and it can contain spaces, so read the full line, not one word.
- Python: input() reads the whole line.
- Java: Scanner's nextLine() or BufferedReader's readLine(). next() stops at the first space.
- C++: getline(cin, s). cin >> s stops at the first space.
- JavaScript: read all of standard input and take the first line.

## Example
Take the line Programming  Is   FUN.
In lower case it is programming  is   fun.
Go through it one character at a time. The vowels are o, a and i in programming, i in is, and u in fun. Print 5.
An empty line, or a line of only spaces, has no vowels, so print 0.

## How to think about it
Loop over the characters: for ch in s in Python, or an index from 0 to the length minus 1 in the other languages. Compare each character with a, e, i, o and u, and add 1 to count when it matches.

Characters compare exactly: A and a are different. So turn each character into lower case first, with lower() in Python, Character.toLowerCase in Java, tolower in C++ and toLowerCase() in JavaScript. Then five comparisons cover both cases.

## Complexity
Time: O(length of the line), one look at each character.
Space: O(length of the line) to hold it.

## Watch out for
- Reading one word. On Programming  Is   FUN, cin >> s or next() reads only Programming and prints 3.
- Upper case. Without lower-casing, the I and the U are missed, which also gives 3.
- An empty line. The answer is 0, not an error.

The grader only sees the number, so a regular expression or a built-in count also passes. Write the loop: it is the pattern behind most string problems.
TEXT,

    'factorial' => <<<'TEXT'
n factorial, written n!, is 1 * 2 * 3 * ... * n. 0! is 1.

A function is a named piece of code you can call. Its parameter is the value you pass in, and its return value is what it hands back. Here, factorial(n) returns n!.

## Example
5! = 1 * 2 * 3 * 4 * 5 = 120.
For 0!, start with result = 1. The loop has nothing to multiply, so result stays 1.
12! = 479001600, 13! = 6227020800 and 20! = 2432902008176640000.

## How to think about it
With a loop: set result = 1, multiply it by every i from 2 to n, and return it.
With recursion, the function calls itself: factorial(n) = n * factorial(n - 1), with factorial(0) = 1 as the stopping point.

The hard part is size. A number type has a largest value, and going past it is called overflow.
- A 32-bit int, the default in Java and C++, holds up to 2,147,483,647. 12! fits, but 13! does not. Java silently wraps around and prints 1932053504; in C++ the result is undefined, and here it printed the same.
- long in Java and long long in C++ hold up to about 9.2 * 10^18, so 20! fits.
- Python integers grow as needed.
- A JavaScript Number stores whole numbers exactly only up to 9,007,199,254,740,991. 19! is past that, so arithmetic may round without warning. Use BigInt instead: write 1n, convert with BigInt(n), and it never rounds.

## Complexity
Time: O(n) multiplications.
Space: O(1) for the loop, O(n) for the recursion's calls.

## Watch out for
- int instead of long. 13 is the first input that breaks it.
- Plain Number in JavaScript. Up to 21!, the results happen to come out right anyway, so the tests can't catch it. Use BigInt so the answer is right by design.

The grader can't tell whether you wrote a function, a loop or recursion, and a table of the 21 answers would also pass. Write the function: the next lessons build on it.
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

    'valid-anagram' => <<<'TEXT'
A hash table stores values by key. A hash function turns each key into a position, so looking up, adding or updating a key takes O(1) on average. Use a dict in Python, a HashMap in Java, a Map in JavaScript, an unordered_map in C++. Two Sum used one to remember positions; here it counts.

Two words are anagrams when they use the same letters the same number of times. Count the letters of the first word, then take away the letters of the second.

## Example
Take "anagram" and "nagaram", both 7 letters.
Counting "anagram" gives a: 3, n: 1, g: 1, r: 1, m: 1.
Take away n, a, g, a, r, a, m. Every count ends at 0, so print yes.
Take "rat" and "car". Counting "rat" gives r: 1, a: 1, t: 1. Take away c: it was never counted, so it drops to -1. Print no.

## How to think about it
Compare the lengths first; different lengths never match. Then add 1 for each letter of s and take 1 away for each letter of t. If a count goes below 0, t uses that letter more often than s. With equal lengths and no negative count, every count is 0 and the answer is yes.

Comparing sorted copies also works, in O(n log n). At 1000 letters both pass, so the grader can't tell them apart. Write the counting version anyway: the next two lessons build on it.

## Complexity
Time: O(n) on average, one map update per letter.
Space: O(1), because the map holds at most 26 keys.

## Watch out for
- Same letters, different counts. "aab" and "abb" have the same length and letters, but the answer is no.
- Empty lines. Either word can be empty; two empty words are anagrams. Read whole lines as in Reverse a string: nextLine() in Java, getline in C++. next() and cin >> s skip an empty line. In JavaScript, split the input into lines before trimming them.

Count with a map in the problem below, and test an empty word first.
TEXT,

    'longest-substring-without-repeating-characters' => <<<'TEXT'
A sliding window is a stretch of the string, between positions left and right, that moves forward instead of being rebuilt. Here it holds no repeated letter. Each step adds the letter at right; if it is already in the window, left jumps just past its earlier copy. A hash map from letter to last-seen position finds that copy in O(1).

## Example
Take "abcabcbb", with left = 0 and best = 0.
right = 0 to 2: a, b, c are new. Window "abc", so best = 3.
right = 3, a: last seen at 0, so left = 1. Window "bca".
right = 4, b: last seen at 1, so left = 2. Window "cab".
right = 5, c: last seen at 2, so left = 3. Window "abc".
right = 6, b: last seen at 4, so left = 5. Window "cb".
right = 7, b: last seen at 6, so left = 7. Window "b".
Print 3.

## How to think about it
For each right: if its letter was last seen at p >= left, set left = p + 1. Then store right as its last position and update best with right - left + 1. left only moves forward, so each letter enters and leaves the window once.

Extending from every start until a letter repeats also passes, since no window holds more than 26 letters. The grader can't force the window here; write it anyway, because it stays O(n) for any alphabet.

## Complexity
Time: O(n), one lookup and one update per letter.
Space: O(1), at most 26 entries.

## Watch out for
- Old positions. In "abba" the last a was seen at 0, but left is already 2. Jumping back to 1 gives 3 instead of 2; "tmmzuxt" gives 6 instead of 5. Only move left forward.
- Emptying the window on a repeat. It drops letters that are still valid: "dvdf" gives 2, but "vdf" has length 3.
- An empty line. The answer is 0.

Write the window in the problem below, and try abba first.
TEXT,

    'subarray-sum-equals-k' => <<<'TEXT'
A prefix sum is the running total so far. If it is sum now and was p earlier, the numbers in between add up to sum - p. So a subarray ending here adds up to k exactly when an earlier total equals sum - k. A hash map from each total to how often it has appeared counts those in O(1) on average.

## Example
Take 1 2 3 with k = 3. The map starts as {0: 1}.
Read 1: sum = 1. Look up -2: not there. Store 1.
Read 2: sum = 3. Look up 0: seen once, so count = 1 (the subarray 1 2). Store 3.
Read 3: sum = 6. Look up 3: seen once, so count = 2 (the subarray 3). Store 6.
Print 2.

## How to think about it
For each number, add it to sum, add the stored count of sum - k to the answer, and only then add 1 to the count of sum. Looking up before storing, as in Two Sum, keeps empty subarrays out, even when k = 0.

The map starts with {0: 1} because before the first number the total is 0: the empty prefix. Without it, subarrays starting at position 0 are missed; for 1 2 3, the subarray 1 2 is lost and the answer is 1.

A sliding window fails here: with negative numbers, growing the window can lower the sum.

Two loops over every start and end take O(n^2), about 500000 sums at n = 1000, and pass here. The grader can't force the map at this size; write it anyway.

## Complexity
Time: O(n) on average, one lookup and one update per number.
Space: O(n) for the map.

## Watch out for
- Zeros. In 0 0 0 with k = 0, all 6 subarrays add up to 0.
- Add the stored count, not 1, and store counts, not positions: one total can match several earlier ones.

Use the map in the problem below, and try 0 0 0 with k = 0 first.
TEXT,

    'height-of-a-bst' => <<<'TEXT'
A binary tree is made of nodes, each with a value and two child pointers, left and right (nothing if missing). The top node is the root.

A binary search tree (BST) keeps one rule at every node: smaller values go in its left subtree, larger ones in its right. To insert v, start at the root and go left if v is smaller than the node, right if it is larger, until that pointer is nothing. Put the new node there.

Nothing rebalances the tree, so its shape depends on the insertion order: 1 2 3 4 5 6 7 gives a chain of 7 nodes.

## Example
Insert 4 2 6 1 3 5 7. 4 is the root, 2 and 6 its children, 1 and 3 under 2, 5 and 7 under 6. Every path from the root down, such as 4 2 1, has 3 nodes, so the height is 3.
Insert 1 5 2 4 3 instead: each value lands one level deeper, so the height is 5.

## How to think about it
The height of a tree is 1 + the larger height of its two subtrees, and an empty tree has height 0: a three-line recursive function.

Without recursion, push (root, 1) on a stack. Pop a pair, keep the largest depth seen, and push each child with depth + 1. A queue, one level at a time, also works.

1,000 sorted values give a chain 1,000 deep. A recursive insert or height then nests about 1,000 calls, past Python's default recursion limit of 1000, and stops with RecursionError. Insert with a loop and use the stack, or call sys.setrecursionlimit(10000) first.

## Complexity
Time: O(n * h) to build, where h is the height: O(n^2) for sorted input. O(n) for the height.
Space: O(n).

## Watch out for
- Count nodes, not edges. One node has height 1, an empty tree 0.
- n = 0. There is no second line and no root.

The grader only sees the number, so it can't tell the stack from a raised limit.
TEXT,

    'lowest-common-ancestor-of-a-bst' => <<<'TEXT'
In a BST, values smaller than a node are in its left subtree and larger ones in its right, so each node tells you which side a value is on.

The lowest common ancestor (LCA) of p and q is the deepest node with both in its subtree. A node counts as its own ancestor: if p is above q, or p = q, the answer is p.

## Example
Insert 6 2 8 0 4 7 9 3 5. 6 is the root with 2 and 8 below it; 0 and 4 hang under 2, 3 and 5 under 4, 7 and 9 under 8.
p = 2, q = 8: 2 is smaller than 6 and 8 is larger, so they split at the root. Print 6.
p = 2, q = 5: at 6 both are smaller, go left. At 2, p is the node itself. Print 2.
p = 5, q = 3: at 6 go left; at 2 both are larger, go right. At 4, 5 is larger and 3 is smaller. Print 4.

## How to think about it
Walk down from the root. If p and q are both smaller than the node, go left; if both are larger, go right. Otherwise this node is the split point: they lie on different sides, or one is the node. Print it.

Comparing both values at every step handles p = q and either order, with no recursion or parent pointers. In an ordinary binary tree, without the BST rule, you would search both subtrees.

## Complexity
Time: O(n * h) to build, where h is the height, then O(h) to walk.
Space: O(n) for the nodes, O(1) for the walk.

## Watch out for
- Order. Don't assume p < q.
- Equality. A node equal to p or q is the split point.
- Deep trees. Sorted input gives a chain 1,000 deep, past Python's default recursion limit of 1000, so insert with a loop.

The grader only sees the value, so comparing the root-to-p and root-to-q paths also passes. Write the walk.
TEXT,

    'delete-from-a-bst' => <<<'TEXT'
Deleting must keep the BST rule. Find the node with value x and its parent, then:
- No children: set the parent's link to nothing.
- One child: point the parent's link at that child.
- Two children: find the successor, the smallest value in the right subtree: step right once, then left while you can. Copy its value into the node, then remove the successor node. It has no left child, so one of the first two cases removes it.

The root has no parent: deleting it makes its child, or nothing, the new root.

## Example
Insert 50 30 70 20 40 60 80 65, then delete 50, the root, which has two children.
Step right to 70, then left to 60. 60 has no left child, so it is the successor.
Copy 60 into the root. The old 60 node has one child, 65, so 70's left link now points at 65.
Preorder prints the root, then the left subtree, then the right: 60 30 20 40 70 65 80.
Deleting 20, a leaf, instead gives 50 30 40 70 60 65 80.

## How to think about it
Keep parent and cur while you search, and the successor's parent too. If the successor is cur's own right child, set cur.right to the successor's right child; otherwise set the successor parent's left to it.

For preorder, use a stack: pop a node, print it, push its right child, then its left.

## Complexity
Time: O(n * h) to build, where h is the height, O(h) to delete and O(n) to print.
Space: O(n).

## Watch out for
- The rule. The largest value in the left subtree also leaves a valid BST, but a different one, and fails here.
- Copying the successor without removing it leaves its value twice.
- Deleting the only node. Print an empty line.
- Deep trees. Sorted input gives a chain 1,000 deep, past Python's default recursion limit.

The grader only compares the preorder, so it can't see whether you moved pointers. Move them anyway.
TEXT,

    'binary-strings' => <<<'TEXT'
A recursive function calls itself on a smaller version of the same problem, and needs a base case: one small enough to answer without another call. Without it, the calls never stop.

Backtracking builds an answer one choice at a time: choose an option, explore by calling yourself for the remaining choices, then undo the choice so the next option starts from the same state. Choose, explore, undo.

Here each step chooses the next character, 0 or 1, and the base case is a string of length n: print it.

## Example
Take n = 2, starting from the empty string.
Choose 0: "0". Explore: choose 0 again, and "00" has length 2: print it. Undo to "0", choose 1, print "01", undo to "0".
Undo the first 0. Choose 1, and the same steps print "10" and "11".
The output is 00, 01, 10, 11.

The calls form a tree, the call tree: the empty string at the root, two children per call (0 and 1), and the 2^n finished strings as leaves. For n = 2 that is 1 + 2 + 4 = 7 calls.

## How to think about it
Keep the chosen characters in a list, cur. If it holds n characters, print them and return. Otherwise, for 0 and then 1: append it (choose), call yourself (explore), and pop it (undo). Trying 0 before 1 puts the lines in increasing order.

Passing cur + bit as a new string needs no undo, since each call gets a copy, but the next two lessons need the list version.

## Complexity
Time: O(2^n * n): 2^n strings of n characters, from 2^(n+1) - 1 calls.
Space: O(n) for cur and the call stack, plus the output.

## Watch out for
- Leading zeros. 001 is a string of length 3; printing the number 1 loses them.
- Forgetting the undo. Without the pop, the second branch starts from the wrong string.

Printing 0 to 2^n - 1 in binary with n digits also passes; the grader only sees the lines. Write the recursion anyway.
TEXT,

    'permutations' => <<<'TEXT'
A permutation of 1 to n uses every number exactly once. Build it one position at a time with the choose, explore, undo pattern, plus two things: the partial answer, cur, holding the numbers placed so far, and a used marker per number, so a number already in cur is skipped.

## Example
Take n = 3, with cur empty and nothing used.
Choose 1: cur = [1]. Choose 2: [1, 2]. Choose 3: [1, 2, 3] has length 3, so print 1 2 3.
Undo 3, then undo 2: back to [1], with 2 and 3 free again. Choose 3: [1, 3]. Choose 2: print 1 3 2.
Undo back to an empty cur. Starting with 2 gives 2 1 3 and 2 3 1, then 3 1 2 and 3 2 1: 3! = 6 in all.

## How to think about it
If cur holds n numbers, print it: that is the base case. Otherwise loop v from 1 to n, and skip v if it is used. If not, mark it used and append it (choose), call yourself (explore), then pop it and clear its mark (undo).

The undo lets one cur and one used array serve the whole call tree. Skip clearing the mark and numbers stay used after you go back: for n = 3, only 1 2 3 is printed.

The order you try the options decides the output order. Trying 1 to n at every position gives lexicographic order: all permutations starting with 1, then 2, and so on. Swapping cur[i] with each later position also makes all n!, but prints 3 2 1 before 3 1 2.

## Complexity
Time: O(n! * n), 720 lines at n = 6.
Space: O(n), plus the output.

## Watch out for
- Numbering. Make used n + 1 long if you index it by value.
- Spaces. Separate the numbers with single spaces, with none at the end of a line.

itertools.permutations in Python and next_permutation in C++ also print the right lines; the grader can't tell. Write the backtracking: N-Queens builds on it.
TEXT,

    'n-queens' => <<<'TEXT'
A queen attacks along its row, column and both diagonals. Place one queen per row, trying the columns 1 to n, and allow a column only if no queen above shares it or a diagonal.

That check is pruning: an attacked column is skipped, with every board below it. When no column works, return: the row above undoes its queen and tries its next column. That is the backtrack.

Along a down-right diagonal row - col is constant; along a down-left one, row + col is. Keep sets of used columns, row - col and row + col: each check is three lookups.

## Example
Take n = 4. Row 1: column 1. Row 2: columns 1 and 2 are attacked, so column 3.
Row 3: columns 1 and 3 are taken, 2 shares row + col = 5 with row 2, and 4 shares row - col = -1. Dead end: back to row 2.
Row 2: column 4. Row 3: column 2. Row 4: all attacked, another dead end. Rows 3 and 2 have no columns left: back to row 1.
Row 1: column 2. Then columns 4, 1 and 3: 2 4 1 3, the first solution.
It also finds 3 1 4 2: print 2, then 2 4 1 3.

## How to think about it
place(row): if every row has a queen, count the solution and save it if it is the first. Otherwise, for each free column, add col, row - col and row + col (choose), call place(row + 1) (explore), then remove all three (undo). Trying columns from 1 up makes the first solution found the one to print.

## Complexity
Time: far below the n^n boards: 35,538 placements at n = 10.
Space: O(n).

## Watch out for
- Undo all three marks, or later rows see false attacks.
- n = 2 and n = 3 have no solution: print 0, then none.

With only ten inputs, a table of the known answers passes every test. The grader can't stop that, but it skips the whole point.
TEXT,

    'count-connected-components' => <<<'TEXT'
A graph is a set of nodes joined by edges. Here edges are undirected: u v works both ways. Store it as an adjacency list, one list of neighbours per node: edge u v adds v to the list of u and u to the list of v.

A connected component is a group of nodes that can all reach each other. Count the walks: start one from each node not yet visited, and mark what it reaches in a visited set.

## Example
Take n = 5 with edges 1 2, 2 3 and 4 5. The lists are 1: [2], 2: [1, 3], 3: [2], 4: [5], 5: [4].
Node 1 is not visited: count = 1. The walk visits 1, 2 and 3.
Skip 2 and 3: they are visited.
Node 4 is not visited: count = 2. The walk visits 4 and 5.
Skip 5. Print 2.

## How to think about it
Loop over the nodes 1 to n. For one that is not visited, add 1 to count, mark it and push it on a stack. Then pop nodes until the stack is empty, pushing and marking each neighbour not visited yet, so no node goes on twice. That is depth-first search (DFS); a queue instead of a stack gives breadth-first search (BFS). Both reach the same nodes.

Union-find, which merges groups edge by edge, also passes; the grader only sees the number.

## Complexity
Time: O(n + m): each node is pushed once and each edge read twice.
Space: O(n + m) for the lists and marks.

## Watch out for
- Nodes with no edges. Each is a component of its own: 4 nodes and no edges give 4.
- Duplicate edges and self-loops. 1 2 twice, or 3 3, are harmless when you check visited.
- Deep recursion. A recursive DFS on the path 1-2-...-1000 nests 1000 calls, past Python's default limit. Use the stack.
- Numbering. Nodes run from 1 to n, so make the lists n + 1 long.

Count the walks in the problem below.
TEXT,

    'number-of-islands' => <<<'TEXT'
A grid is a graph you never have to build. Each cell is a node; its neighbours are the cells up, down, left and right. Keep the four moves as offsets, (-1, 0), (1, 0), (0, -1) and (0, 1), added to the row and column. A move counts only if the new cell is inside the grid: 0 <= row < r and 0 <= col < c.

An island is a group of connected 1 cells, so counting islands is counting components.

## Example
Take this 4 by 5 grid:
    11000
    11000
    00100
    00011
Scan row by row. (0, 0) is an unvisited 1: count = 1. The search marks (0, 0), (0, 1), (1, 0) and (1, 1).
(2, 2) is an unvisited 1 with no 1 neighbours: count = 2.
(3, 3) is an unvisited 1: count = 3. The search marks (3, 3) and (3, 4).
Print 3.

## How to think about it
Loop over every cell. At a 1 not yet visited, add 1 to count and search from it with a stack or a queue, marking each 1 you reach: in a visited grid, or by changing it to 0.

A recursive DFS is shorter, but on a 50 by 50 grid of 1s it nests about 2,500 calls. Python's default recursion limit is 1000, so it stops with RecursionError. Use an explicit stack or a queue, or raise the limit with sys.setrecursionlimit. In this grader, Java, JavaScript and C++ coped with 2,500 nested calls, but the stack is safe everywhere.

## Complexity
Time: O(r * c): each cell is handled a few times.
Space: O(r * c) for the marks and the stack.

## Watch out for
- Bounds. Check them before reading a cell.
- Diagonals are not neighbours. In the grid 101, 010, 101 the 1s touch only at corners, so the answer is 5.
- Mark when you push, or a cell can go on the stack more than once.

The grader only checks the count. Try a full grid of 1s before you submit.
TEXT,

    'shortest-path-in-a-grid' => <<<'TEXT'
Breadth-first search (BFS) explores in rings: the start, then every cell 1 move away, then 2 moves away, and so on. A queue does this: cells come out in the order they went in. So the first time BFS reaches a cell, it has used the fewest moves. Store that number in dist, a grid that starts at -1.

## Example
Take this 4 by 5 grid, where 1 is a wall:
    00000
    00000
    00001
    00100
dist[0][0] = 0. Ring by ring, (2, 3) gets 5, (3, 3) gets 6 and the end, (3, 4), gets 7. Print 7.
A DFS that tries up, down, left, right in that order runs down column 0, up column 1, down column 2, and first reaches the end after 13 moves.

## How to think about it
If the start or the end is a wall, print -1. Otherwise set the start's dist to 0 and queue it. Take cells from the front; for each neighbour that is inside the grid, open and still -1, set its dist to the current dist + 1 and add it to the back. When the queue is empty, print dist of the end; it stays -1 if no path exists.

DFS follows one path as deep as it can, so it can reach the end by a detour, and the cells it marked block the shorter routes. Trying every path finds the minimum, but there are exponentially many.

Use collections.deque in Python, ArrayDeque in Java, std::queue in C++, and an array with a head index in JavaScript. At 2,500 cells, list.pop(0) and shift() also pass; the grader can't see which queue you used.

## Complexity
Time: O(r * c): each cell enters the queue at most once.
Space: O(r * c) for dist and the queue.

## Watch out for
- A 1 by 1 open grid. The start is the end, so the answer is 0.
- Mark cells when you add them, not when you take them out.

Write the queue and the dist grid in the problem below.
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
