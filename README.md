# christmasTreeDetector

A script coded within a few hours for the Stack Overflow competition "How to detect a Christmas Tree?".
http://stackoverflow.com/questions/20772893/how-to-detect-a-christmas-tree/20893881#20893881

My original description:

Using a quite different approach from what I've seen, I created a php script that detects christmas trees by their lights. The result ist always a symmetrical triangle, and if necessary numeric values like the angle ("fatness") of the tree.

The biggest threat to this algorithm obviously are lights next to (in great numbers) or in front of the tree (the greater problem until further optimization). Edit (added): What it can't do: Find out if there's a christmas tree or not, find multiple christmas trees in one image, correctly detect a cristmas tree in the middle of Las Vegas, detect christmas trees that are heavily bent, upside-down or chopped down... ;)

The different stages are:

* Calculate the added brightness (R+G+B) for each pixel
* Add up this value of all 8 neighbouring pixels on top of each pixel
* Rank all pixels by this value (brightest first) - I know, not really subtle...
* Choose N of these, starting from the top, skipping ones that are too close
* Calculate the median of these top N (gives us the approximate center of the tree)
* Start from the median position upwards in a widening search beam for the topmost light from the selected brightest ones (people tend to put at least one light at the very top)
* From there, imagine lines going 60 degrees left and right downwards (christmas trees shouldn't be that fat)
* Decrease those 60 degrees until 20% of the brightest lights are outside this triangle
* Find the light at the very bottom of the triangle, giving you the lower horizontal border of the tree
* Done

Explanation of the markings:

* Big red cross in the center of the tree: Median of the top N brightest lights
* Dotted line from there upwards: "search beam" for the top of the tree
* Smaller red cross: top of the tree
* Really small red crosses: All of the top N brightest lights
* Red triangle: D'uh!
