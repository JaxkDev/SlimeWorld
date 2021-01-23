# SlimeWorld
A bad implementation of Hypixels slime format, 
this should not be used in production simply a test of the slime format documented in a hypixel dev post.

# Pro's:
- Uses zstd compression meaning loading and saving is much faster and efficient than zlib [TODO, Comparisons]
- Some NBT has been removed in accordance with the [Slime Format](https://pastebin.com/raw/EVCNAmkw) meaning less data is wasted with naming fields that we already know are in that position.

# Con's:
- Experimental format.
- Was implemented by Jaxk
- Other world formats must be manually converted with external tools (for now)
- Entire world (entities, tiles and chunks) are all stored in memory while world is loaded.

Probably other Pro/Con's but I just can't think of any right now...

# Requirements:
- php >= 7.3.0
- ext-zstd >= 0.8.0