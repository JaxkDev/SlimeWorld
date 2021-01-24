# SlimeWorld
A bad implementation of Hypixels slime format, 
this should not be used in production simply a test of the slime format documented in a hypixel dev post.

Several issues with current implementation, this was done in a day or two worth of work dont expect it to work perfectly...


# Pro's:
- Uses zstd compression meaning loading and saving is much faster and efficient than zlib
  - 81 Chunks (initial terrain generated) only takes ~30KB
- Some NBT has been removed in accordance with the [Slime Format](https://pastebin.com/raw/EVCNAmkw) meaning less data is wasted with naming fields that we already know are in that position.

# Con's:
- Cannot store large wolds (Due to single file format and memory limitations)
- Experimental format.
- Was implemented by Jaxk
- Other world formats must be manually converted with external tools (for now)
- Entire world (entities, tiles and chunks) are all stored in memory while world is loaded.

Probably other Pro/Con's but I just can't think of any right now...

# Requirements:
- php >= 7.3.0
- ext-zstd >= 0.8.0