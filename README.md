The Stringex Client: Indexing in Stringent Environments

Overview
========

First, let's define a stringent environment:
* environments where you can only create a limited number of files, normally, inside only one folder
* environments where you should not re-write files too often (Dropbox API, etc.)
* environments where each read and write should require updates of as few files as possible -- same about the basic lookup routine
* environments where to update a file you need to rewrite it from scratch (Dropbox API)

The Stringex client, therefore is an indexer which fits the above description.  This particular code is the PHP implementation,  but I am planning to write one in JS, thus making it possible to run the whole thing as a standalone application in a browser. 


Installation
=========
1. Unpack *ajaxkit.rar*   -- it has all the libraries and will be detected automatically by scripts
2. Unpack *Zend.rar* if you want to run test the Lucene part
3. See *commandline.txt* for command line examples and explanations. 

Remember that this code is a research project, not a running version. 


Content
==========

The core functionality is in 
* _Stringex.php_	-- the entire PHP indexer, Stringex() class is the only one you shold use
* _functions.php_  has FilesystemWatch class which is how I monitored filesystem changes under Stringex versus Lucene

Dependenices: NONE, should work with basic PHP5 installation. 


Dataset
==========
For obvious reasons I cannot give you the 5K item dataset I used for the project, but I give you the *dataset.bz64jsonl* with 10 lines representing my own papers.  


Open Issues
==========
* No tokenization, so far it is only the full-string indexing, but it is sufficient for the purpose -- browser-based indexes of fixed data items, like paper information on scientific portals.  In such environments, you normally do not need to tokenize strings while browsing function is important. 
* No runtime optimization.  You have to configure key and mask lengths for hashing manually.  Will be optimized in future releases.
