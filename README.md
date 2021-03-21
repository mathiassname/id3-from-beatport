# ID3 from Beatport

![Maintained][maintained-badge]
[![License](http://img.shields.io/badge/Licence-GPL-brightgreen.svg)](LICENSE)

[![Watch on GitHub][github-watch-badge]][github-watch]
[![Star on GitHub][github-star-badge]][github-star]
[![Tweet][twitter-badge]][twitter]

Simple script to scrap some ID3 information from Beatport.

I greated this PHP script after I found no solution to tag my songs with valid ID3 tags. I loved [Beatport Pro](https://www.beatport.com/mac/index.html) and the possibility to tag all tracks in my collection. But now, the program is no more available.

### How it works?

I take an ID of a Beatport track and parse the information direct from the website. With this information the script rename all track files and set the MP3 ID3 tags.

### How to run?

1. Clone or download (and extract) the script to your local machine from the [Github repository](https://github.com/mathiassname/id3-from-beatport)
1. Open the terminal / command line and go to the directory
1. Copy some MP3 files in the included *track/* folder
1. Rename all MP3 files with the following schema: ```<Beatport ID>_<some name>.mp3``` (e.g. ```123456789_lorem ipsum.mp3```)
1. Run the PHP script with the following command: ```php index.php```

### Output file names

The script rename all the input MP3 tracks. The schema of the renamig is:

```<Beatport ID>_<Artist>_<Track title>_<Genre>.mp3```

### License

This script is published under the GNU General Public License v3 without any warranty.

### How to support me?

It exists many ways to support free software:

* Report issues: https://github.com/mathiassname/id3-from-beatport/issues
* Share *ID3 from Beatport* with your friends
* Buy me a coffee ☕️: https://www.paypal.com/donate/?hosted_button_id=G5QWZVJSCTRKA 

[github-watch-badge]: https://img.shields.io/github/watchers/mathiassname/id3-from-beatport.svg?style=social
[github-watch]: https://github.com/mathiassname/id3-from-beatport/watchers
[github-star-badge]: https://img.shields.io/github/stars/mathiassname/id3-from-beatport.svg?style=social
[github-star]: https://github.com/mathiassname/id3-from-beatport/stargazers
[twitter]: https://twitter.com/intent/tweet?text=Check%20out%20ID3%20from%20Beatport!%20https://github.com/mathiassname/id3-from-beatport%20%F0%9F%91%8D
[twitter-badge]: https://img.shields.io/twitter/url/https/github.com/mathiassname/id3-from-beatport.svg?style=social
[maintained-badge]: https://img.shields.io/badge/maintained-yes-brightgreen
