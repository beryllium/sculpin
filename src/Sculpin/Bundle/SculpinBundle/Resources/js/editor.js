var SculpinEditor = {
    init: function () {
        this.renderBar();
    },

    renderBar: function () {
        // select body element for interface injection
        var body = document.getElementsByTagName('body')[0];

        // render bottom bar with edit button
        body.innerHTML += '<div id="SCULPIN_BOTTOM_BAR">' +
            '<div><strong>Sculpin</strong> <em>In-Browser Editor</em> ' +
            '<a href="https://sculpin.io/documentation/sources">[ Documentation ]</a></div>' +
            '<div class="controls">' +
            '<span><strong>Current Disk Path:</strong> '+SCULPIN_EDITOR_METADATA.diskPath+'</span>' +
            '<button id="SCULPIN_EDIT_BUTTON">Edit This Page</button></div>' +
            '</div>';
        this.registerListeners();
    },

    renderEditor: function () {
        // replace bar with editor
        document.getElementById('SCULPIN_BOTTOM_BAR').style.display = 'none';

        // load editor box with content
        var body = document.getElementsByTagName('body')[0];
        body.innerHTML += '<div id="SCULPIN_EDIT_PANEL">' +
            '<h3>Editing <small>'+SCULPIN_EDITOR_METADATA.url+'</small></h3>' +
            'Not the file you want to edit? Choose a different one: <form><select id="SCULPIN_FILE_SELECTOR" name="file-selector">' +
            Object.entries(SCULPIN_EDITOR_METADATA.sourceMap).map(
                (item, i) => '<option value="' + item[0] + '"' + (item[0] === SCULPIN_EDITOR_METADATA.diskPath ? ' SELECTED' : '') + '>' + item[0] + '</option>'
            ).join('') +
            '</select></form>' +
            '<textarea id="SCULPIN_EDIT_TEXTAREA">' + SCULPIN_EDITOR_METADATA.content + '</textarea>' +
            '<div class="controls">' +
            '<button id="SCULPIN_CANCEL_CHANGES">Cancel</button> ' +
            '<button id="SCULPIN_SAVE_CHANGES">Save Changes</button>' +
            '</div></div>';

        this.registerListeners();
    },

    registerListeners: function () {
        let editButton = document.getElementById("SCULPIN_EDIT_BUTTON");
        let saveButton = document.getElementById("SCULPIN_SAVE_CHANGES");
        let cancelButton = document.getElementById("SCULPIN_CANCEL_CHANGES");
        let fileSelectorForm = document.getElementById('SCULPIN_FILE_SELECTOR');

        editButton && editButton.addEventListener('click', function () {
            var editor = document.getElementById('SCULPIN_EDIT_PANEL');
            var menuBar = document.getElementById('SCULPIN_BOTTOM_BAR');

            if (editor && editor.length > 0) {
                editor.style.display = 'block';
                menuBar.style.display = 'none';

                SculpinEditor.registerListeners();

                return;
            }

            SculpinEditor.renderEditor();
        });

        // create save button listener

        saveButton && saveButton.addEventListener('click', function () {
            SculpinEditor.saveChanges();
        });

        fileSelectorForm && fileSelectorForm.addEventListener('change', function (e) {
            SculpinEditor.switchFile(e);
        });

        // @todo Cancel button re-registration is not working
        cancelButton && cancelButton.addEventListener('click', function () {
            console.log('Clicked Cancel');
            // @todo check if the content has changed and ask the user to confirm if they want to discard their changes
            document.getElementById('SCULPIN_BOTTOM_BAR').style.display = 'block';
            document.getElementById('SCULPIN_EDIT_PANEL').style.display = 'none';

            SculpinEditor.registerListeners();
        });
    },

    saveChanges: function () {
        var content = document.getElementById('SCULPIN_EDIT_TEXTAREA').value;

        if (content === SCULPIN_EDITOR_METADATA.content) {
            // nothing has changed
            // pretend like something changed
            document.location.reload();
            return;
        }

        // PUT content to the appropriate spot
        // this logic is temporary. Would be nice to use local storage to make sure that nothing gets lost if
        // user navs away. Also, XHR synchronous usage is deprecated, would be nice to either redo this as a
        // form submit or await the result of the async version.
        var xmlHttp = new XMLHttpRequest();

        xmlHttp.open('PUT', '/_SCULPIN_/update');
        xmlHttp.setRequestHeader('Content-Type', 'application/json');
        xmlHttp.onload = function (e) {
            if (xmlHttp.readyState === 4) {
                if (xmlHttp.status === 200 || xmlHttp.status === 307) {
                    SculpinEditor.watchForChanges(SCULPIN_EDITOR_METADATA.url, SCULPIN_EDITOR_METADATA.contentHash)
                } else {
                    console.error(xmlHttp.statusText);
                }
            }
        };
        xmlHttp.send(JSON.stringify({
            'diskPath': SCULPIN_EDITOR_METADATA.diskPath,
            'url': SCULPIN_EDITOR_METADATA.url,
            'path': window.location.pathname,
            'content': content,
            'contentHash': SCULPIN_EDITOR_METADATA.contentHash
        }));
    },

    // Watches the hash every 500ms for 10 attempts, then gives up and reloads
    watchForChanges: function (url, oldHash) {
        let hashwatcherId;
        let hashwatcherCounter = 0;

        hashwatcherId = setInterval(() => {
            if (url.length === 0 || oldHash === undefined) {
                // The edited content does not correspond to a specific URL
                // Wait a few seconds and then trigger a regular reload
                clearInterval(hashwatcherId);
                setTimeout(() => document.location.reload(), 3000);
                return;
            }

            // @todo maybe update the document.location if the `url` is not blank & doesn't
            //       match (or isn't contained in) document.location
            if (hashwatcherCounter++ > 10) {
                console.log('Giving up on checking the hash; reloading current location');
                clearInterval(hashwatcherId);
                document.location.reload();
            }

            fetch ('/_SCULPIN_/hash?url=' + url + '&oldHash=' + oldHash, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            }).then( response => {
                if (response.ok) {
                    // fetch that body
                    return response.json();
                }
            }).then(data => {
                console.log(
                    ' Old Hash: ' + oldHash +
                    ' New Hash: ' + data.hash +
                    ' For URL: ' + url,
                    data
                );
                // check that hash has changed from oldHash
                // if so, reload the current page
                if (data.hash !== oldHash) {
                    document.location.reload();

                    return;
                }
            });
        }, 500);
    },

    switchFile: (e) => {
        // Ensure that the selected value exists in the SourceMap (source/* files, unprocessed) or PathMap (processed files mapped to the SourceMap)
        let newFilePath = e.currentTarget.value;
        let newFileSource = SCULPIN_EDITOR_METADATA.sourceMap[newFilePath];

        if (newFileSource === undefined) {
            console.log('Encountered an error: source map not found for ' + newFilePath + ', CANNOT SWITCH FILE');
        }

        // Check that it is OK to rewrite the Editor (i.e., the content matches the current content hash)
        let content = document.getElementById('SCULPIN_EDIT_TEXTAREA').value;
        if (content !== SCULPIN_EDITOR_METADATA.content) {
            console.log('Encountered an error: CONTENT MISMATCH, EDITOR IS DIRTY, CANNOT SWITCH FILE');
            return;
        }

        // Re-fetch Metadata Var for the new selection
        fetch('/_SCULPIN_/metadata?source=' + newFilePath)
            .then(response => {
                if (!response.ok) {
                    throw Error(response.statusText);
                }

                return response.json();
            })
            .then(data => {
                SCULPIN_EDITOR_METADATA = data;

                // Write new content to Editor Textarea
                SculpinEditor.refreshEditor();
            })
            .catch(err => console.log('Exception while updating metadata', err));
    },
    refreshEditor: () => {
        let editBox = document.getElementById('SCULPIN_EDIT_TEXTAREA');
        let editFilename = document.querySelectorAll('#SCULPIN_EDIT_PANEL > h3 > small')[0];

        editBox.value = SCULPIN_EDITOR_METADATA.content;
        editFilename.innerHTML = SCULPIN_EDITOR_METADATA.diskPath;
    }
};

SculpinEditor.init();
