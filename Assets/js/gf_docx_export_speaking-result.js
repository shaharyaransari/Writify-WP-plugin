/**
 * Script Name: Docx Export for Speaking
 * Version: 1.0.3
 * Last Updated: 4-08-2024
 * Author: bi1101
 * Description: Export the result page as docx files with comments.
 */
var generatedBlob = null;
var globalCommentCounter = 0;

async function fetchStylesXML() {
    const response = await fetch('https://cdn.jsdelivr.net/gh/bi1101/Docx-Export-for-Writify/styles.xml');
    const xmlText = await response.text();
    return xmlText;
}

function createVocabSectionWithComments(rawComments) {
    const { Paragraph, TextRun, HeadingLevel, CommentRangeStart, CommentRangeEnd, CommentReference } = docx;

    // Get vocabulary score and prepare heading
    const vocabScore = document.querySelector('#vocab_score_wrap').innerText;
    const headingText = `Vocabulary: ${vocabScore}`;
    
    const sectionChildren = [
        new Paragraph({
            children: [new TextRun(headingText)],
            heading: HeadingLevel.HEADING_1
        })
    ];

    // Get transcript elements
    const transcriptWrap = document.querySelector('#vocab-transcript-wrap');
    const fileBlocks = transcriptWrap.querySelectorAll('.file-block');

    fileBlocks.forEach(fileblock => {
        // Add file block heading (file title)
        const fileTitle = fileblock.querySelector('.file-title').innerText;
        sectionChildren.push(
            new Paragraph({
                children: [new TextRun({text: fileTitle, bold: true})]
            })
        );
        const paraChildren = [];
        // Process each transcript in the file block
        const transcripts = fileblock.querySelectorAll('p.transcript-text');
        transcripts.forEach((transcriptEl,index) => {
            let transcriptText = transcriptEl.textContent;
            if(index == 0){
                transcriptText = transcriptEl.textContent.trim();
            }
            const errors = transcriptEl.querySelectorAll('span');
            let currentPosition = 0;

            if (errors.length > 0) {
                // Process each error in the transcript
                errors.forEach(error => {
                    const errorText = error.textContent;
                    const errorID = error.id;
                    const suggestionID = errorID.replace('ERROR_', 'VOCAB_ERROR_');

                    // Find corresponding comment from rawComments
                    const currentErrorComment = rawComments.find(comment => comment.errorId === suggestionID);

                    if (currentErrorComment) {
                        const commentStartPos = transcriptText.toLowerCase().indexOf(errorText.toLowerCase(), currentPosition);
                        
                        if (commentStartPos >= 0) {
                            // Add the text before the comment
                            if (commentStartPos > currentPosition) {
                                paraChildren.push(new TextRun(transcriptText.slice(currentPosition, commentStartPos)));
                            }

                            // Add the comment with the error
                            paraChildren.push(new CommentRangeStart(currentErrorComment.commentCounter));
                            paraChildren.push(new TextRun(currentErrorComment.orignal));
                            paraChildren.push(new CommentRangeEnd(currentErrorComment.commentCounter));
                            paraChildren.push(new TextRun({
                                children: [new CommentReference(currentErrorComment.commentCounter)]
                            }));

                            // Update current position after the comment
                            currentPosition = commentStartPos + currentErrorComment.orignal.length;
                        }
                    }
                });

                // Add any remaining text after the last comment
                if (currentPosition < transcriptText.length) {
                    paraChildren.push(new TextRun(transcriptText.slice(currentPosition)));
                }
            } else {
                // If no errors, add the whole transcript text
                paraChildren.push(new TextRun(transcriptText));
            }
        });
        // Push the paragraph with content and comments
        sectionChildren.push(new Paragraph({
            children: paraChildren,
            indent: {
                firstLine: 0, // Set first line indent to 0 to remove any indentation
            }
        }));
    });
    
    return sectionChildren;
}

function createGrammerSectionWithComments(rawComments){
    const { Paragraph, TextRun, HeadingLevel, CommentRangeStart, CommentRangeEnd, CommentReference } = docx;

    // Get vocabulary score and prepare heading
    const grammerScore = document.querySelector('#grammer_score_wrap').innerText;
    const headingText = `Grammar: ${grammerScore}`;
    
    const sectionChildren = [
        new Paragraph({
            children: [new TextRun(headingText)],
            heading: HeadingLevel.HEADING_1
        })
    ];

    // Get transcript elements
    const transcriptWrap = document.querySelector('#grammer-transcript-wrap');
    const fileBlocks = transcriptWrap.querySelectorAll('.file-block');

    fileBlocks.forEach(fileblock => {
        // Add file block heading (file title)
        const fileTitle = fileblock.querySelector('.file-title').innerText;
        sectionChildren.push(
            new Paragraph({
                children: [new TextRun({text: fileTitle, bold: true})]
            })
        );
        const paraChildren = [];
        // Process each transcript in the file block
        const transcripts = fileblock.querySelectorAll('p.transcript-text');
        transcripts.forEach((transcriptEl,index) => {
            let transcriptText = transcriptEl.textContent;
            if(index == 0){
                transcriptText = transcriptEl.textContent.trim();
            }
            const errors = transcriptEl.querySelectorAll('span');
            let currentPosition = 0;

            if (errors.length > 0) {
                // Process each error in the transcript
                errors.forEach(error => {
                    const errorText = error.textContent;
                    const errorID = error.id;
                    const suggestionID = errorID.replace('ERROR_', 'GRAMMER_ERROR_');

                    // Find corresponding comment from rawComments
                    const currentErrorComment = rawComments.find(comment => comment.errorId === suggestionID);
                    console.log(currentErrorComment);
                    if (currentErrorComment) {
                        const commentStartPos = transcriptText.toLowerCase().indexOf(errorText.toLowerCase(), currentPosition);
                        
                        if (commentStartPos >= 0) {
                            // Add the text before the comment
                            if (commentStartPos > currentPosition) {
                                paraChildren.push(new TextRun(transcriptText.slice(currentPosition, commentStartPos)));
                            }

                            // Add the comment with the error
                            paraChildren.push(new CommentRangeStart(currentErrorComment.commentCounter));
                            paraChildren.push(new TextRun(currentErrorComment.orignal));
                            paraChildren.push(new CommentRangeEnd(currentErrorComment.commentCounter));
                            paraChildren.push(new TextRun({
                                children: [new CommentReference(currentErrorComment.commentCounter)]
                            }));

                            // Update current position after the comment
                            currentPosition = commentStartPos + currentErrorComment.orignal.length;
                        }
                    }
                });

                // Add any remaining text after the last comment
                if (currentPosition < transcriptText.length) {
                    paraChildren.push(new TextRun(transcriptText.slice(currentPosition)));
                }
            } else {
                // If no errors, add the whole transcript text
                paraChildren.push(new TextRun(transcriptText));
            }
        });
        // Push the paragraph with content and comments
        sectionChildren.push(new Paragraph({
            children: paraChildren,
            indent: {
                firstLine: 0, // Set first line indent to 0 to remove any indentation
            }
        }));
    });
    
    return sectionChildren;
}

function createPronunSectionWithComments(rawComments){
    
    const { Paragraph, TextRun, HeadingLevel, CommentRangeStart, CommentRangeEnd, CommentReference } = docx;

    // Get vocabulary score and prepare heading
    const pronunScore = document.querySelector('#pronun_score_wrap').innerText;
    const headingText = `Pronunciation: ${pronunScore}`;
    
    const sectionChildren = [
        new Paragraph({
            children: [new TextRun(headingText)],
            heading: HeadingLevel.HEADING_1
        })
    ];

    // Get transcript elements
    const transcriptWrap = document.querySelector('#pronun-transcript-wrap');
    const fileBlocks = transcriptWrap.querySelectorAll('.file-block');

    fileBlocks.forEach(fileblock => {
        // Add file block heading (file title)
        const fileTitle = fileblock.querySelector('.file-title').innerText;
        sectionChildren.push(
            new Paragraph({
                children: [new TextRun({text: fileTitle, bold: true})]
            })
        );
        const paraChildren = [];
        // Process each transcript in the file block
        const transcripts = fileblock.querySelectorAll('p.transcript-text');
        transcripts.forEach(transcriptEl => {
            const transcriptText = getTextContent(transcriptEl);
            const errors = transcriptEl.querySelectorAll('span.pronun-error');
            let currentPosition = 0;

            if (errors.length > 0) {
                // Process each error in the transcript
                errors.forEach(error => {
                    const errorText = error.textContent;
                    const errorID = error.id;
                    const suggestionID = errorID.replace('PRONUN_ERROR_', 'SIDEPANEL_PRONUN_ERROR_');

                    // Find corresponding comment from rawComments
                    const currentErrorComment = rawComments.find(comment => comment.errorId === suggestionID);
                    console.log(currentErrorComment);
                    if (currentErrorComment) {
                        const commentStartPos = transcriptText.toLowerCase().indexOf(errorText.toLowerCase(), currentPosition);
                        
                        if (commentStartPos >= 0) {
                            // Add the text before the comment
                            if (commentStartPos > currentPosition) {
                                paraChildren.push(new TextRun(transcriptText.slice(currentPosition, commentStartPos)));
                            }

                            // Add the comment with the error
                            paraChildren.push(new CommentRangeStart(currentErrorComment.commentCounter));
                            paraChildren.push(new TextRun(`${currentErrorComment.orignal}`));
                            paraChildren.push(new CommentRangeEnd(currentErrorComment.commentCounter));
                            paraChildren.push(new TextRun({
                                children: [new CommentReference(currentErrorComment.commentCounter)]
                            }));

                            // Update current position after the comment
                            currentPosition = commentStartPos + currentErrorComment.orignal.length;
                        }
                    }
                });

                // Add any remaining text after the last comment
                if (currentPosition < transcriptText.length) {
                    paraChildren.push(new TextRun(transcriptText.slice(currentPosition)));
                }
            } else {
                // If no errors, add the whole transcript text
                paraChildren.push(new TextRun(transcriptText));
            }
        });
        // Push the paragraph with content and comments
        sectionChildren.push(new Paragraph({
             children: paraChildren,
             indent: {
                firstLine: 0, // Set first line indent to 0 to remove any indentation
             }
            }));
    });
    
    return sectionChildren;
}

function getTextContent(element) {
    let textWithSpaces = '';

    element.childNodes.forEach(node => {
        if (node.nodeType === Node.TEXT_NODE) {
            // Append text nodes directly
            textWithSpaces += node.textContent;
        } else if (node.nodeType === Node.ELEMENT_NODE && node.tagName === 'SPAN') {
            // Append span text and add a space after each span
            textWithSpaces += node.textContent + ' ';
        }
    });

    // Trim to remove any trailing spaces
    return textWithSpaces.trim();
}

function createFluencySection(){
    const { Paragraph, TextRun, HeadingLevel } = docx;
    let score = document.querySelector('#fluency_score_wrap').innerText;
    let headingText = `Fluency: ${score}`;
    let wpm = document.querySelector('#wpmValue span').textContent;
    let wpmData = wpm.match(/(\d+)\s*WPM\s*(.*)/);
    console.log(wpmData);
    wpm = wpmData[1];
    let speedText = wpmData[2];
    let wpmColor;
    let fillerWordsColor = '#ffc000';
    let badPause = new TextRun({
        text: '_',
        color: '#FF4949'
    });
    let missedPause = new TextRun({
        text: '_',
        color: '#ffc000'
    });
    if(parseInt(wpm) < 110){
        // Red if less then 110
        wpmColor = '#FF4949';
    }else if(parseInt(wpm) > 150){
        // Orange if greater then 150
        wpmColor = '#FF822E';
    }else{
        // Green if between 110 and 150
        wpmColor = '#13CE66';
    }
    let sectionChildren = [
        new Paragraph({
            children: [new TextRun(headingText)],
            heading: HeadingLevel.HEADING_1
        }),
        new Paragraph({
            children: [
                new TextRun('Speaking rate:'),
                new TextRun({
                    text: wpm,
                    color: wpmColor
                })
            ]

        }),
        new Paragraph({
            children: [
                new TextRun('Your Pace Was '),
                new TextRun({
                    text: speedText,
                    color: wpmColor
                }),
                new TextRun(' during this recording'),
            ]
        }),
        new Paragraph({
            alignment: docx.AlignmentType.RIGHT,
            children: [
                new TextRun('Note: '),
                new TextRun({
                    text: 'Uhm, uh',
                    color: fillerWordsColor
                }),
                new TextRun('      '),
                badPause,
                new TextRun('Bad Pause'),
                new TextRun('      '),
                missedPause,
                new TextRun('Missed Pause'),
            ]
        }),
    ];

    // Get transcript elements
    const transcriptWrap = document.querySelector('#fluency-transcript-wrap');
    const fileBlocks = transcriptWrap.querySelectorAll('.file-block');

    fileBlocks.forEach(fileblock => {
        // Add file block heading (file title)
        const fileTitle = fileblock.querySelector('.file-title').innerText;
        sectionChildren.push(
            new Paragraph({
                children: [new TextRun({text: fileTitle, bold: true})]
            })
        );
        const paraChildren = [];
        // Process each transcript in the file block
        const transcripts = fileblock.querySelectorAll('p.transcript-text');
        transcripts.forEach(transcriptEl => {
            let transcriptText = transcriptEl.textContent;
            transcriptText = transcriptText.replace(/\s+/g, ' ').trim();
            const errorSpans = transcriptEl.querySelectorAll('span');
            let currentPosition = 0;
            if (errorSpans.length > 0) {
                // Process each error in the transcript
                errorSpans.forEach((error,index) => {
                    console.log(error);
                    let isPauseError = false;
                    let isBadPause = false;
                    let isMissingPause = false;
                    if(error.classList.contains('pause-error')){
                        isPauseError = true;
                        if(error.classList.contains('unexpectedbreak-pause')){
                            isBadPause = true;
                        }else{
                            isMissingPause = true;
                        }
                    }
                    let errorText = '';
                    if(isPauseError){
                        // Get the index of the pause error span in the transcript text
                        const textWithPlaceholders = getTextWithPlaceholders(transcriptEl);
                        let placeholder = `<<SPAN_${index}>>`;
                        let pauseErrorStartPos = textWithPlaceholders.indexOf(placeholder, currentPosition);
                        if(index > 0){
                            pauseErrorStartPos = pauseErrorStartPos - (placeholder.length + 1); // +1 for Space
                        }
                        console.log(`Current Position: ${currentPosition} Error Position :${pauseErrorStartPos} in ${textWithPlaceholders}`);
                        if (pauseErrorStartPos >= 0) {
                            // Add the text before the comment
                            if (pauseErrorStartPos > currentPosition) {
                                let slicedText = transcriptText.slice(currentPosition, pauseErrorStartPos);
                                console.log(slicedText);
                                paraChildren.push(new TextRun(slicedText));
                            }
                            if(isMissingPause){
                                paraChildren.push(missedPause);
                            }else if(isBadPause){
                                paraChildren.push(badPause);
                            }
                            // Update current position
                            currentPosition = pauseErrorStartPos;
                        }
                    }else{
                        const fillerWordStartPos = transcriptText.toLowerCase().indexOf(errorText.toLowerCase(), currentPosition);
                        if (fillerWordStartPos >= 0) {
                            // Add the text before the comment
                            if (fillerWordStartPos > currentPosition) {
                                paraChildren.push(new TextRun(transcriptText.slice(currentPosition, fillerWordStartPos)));
                            }
                            paraChildren.push(new TextRun({
                                text: `${errorText}`,
                                color: fillerWordsColor
                            }));
                            // Update current position after the comment
                            currentPosition = fillerWordStartPos + errorText.length;
                        }
                    }
                });
                // Add any remaining text after the last comment
                if (currentPosition < transcriptText.length) {
                    paraChildren.push(new TextRun(transcriptText.slice(currentPosition)));
                }
            } else {
                // If no errors, add the whole transcript text
                paraChildren.push(new TextRun(transcriptText));
            }
        });
        // Push the paragraph with content and comments
        sectionChildren.push(new Paragraph({ 
            children: paraChildren,
            indent: {
                firstLine: 0, // Set first line indent to 0 to remove any indentation
             }
        }));
    });
    return sectionChildren;
}

function getRawVocabComments(){
    const suggestions = document.querySelectorAll('#vocab-suggestions .upgrade_vocab');
    let comments = [];
    suggestions.forEach(suggestionEl =>{
        let orignal = suggestionEl.querySelector('.original-vocab').innerText;
        let improved = suggestionEl.querySelector('.improved-vocab').innerText;
        let explanation = suggestionEl.querySelector('.explanation').innerText;
        let commentObj = {
            // Specific to Vocab
            orignal : orignal,
            improved : improved,
            explanation : explanation,
            // Commont Properties
            errorId : suggestionEl.id,
            commentType : 'vocab',
            commentedText : orignal,
            commentContent: `${orignal} -> ${improved} \n Explaination: ${explanation}`,
            commentCounter: globalCommentCounter
        }
        globalCommentCounter++;
        comments.push(commentObj);
    });

    return comments;
}

function getRawGrammerComments(){
    const suggestions = document.querySelectorAll('#grammer-suggestions .grammer_error');
    let comments = [];
    suggestions.forEach(suggestionEl =>{
        let orignal = suggestionEl.querySelector('.original-grammer-word').innerText;
        let improved = '';
        let improvedWords = suggestionEl.querySelectorAll('.improved-grammer-word span');
        improvedWords.forEach((improvedWord,index) => {
            improved += ` ${improvedWord.innerText}`;
            if(index != (improvedWords.length - 1)){
                improved += ',';
            }
        });
        let explanation = suggestionEl.querySelector('.explanation').innerText;
        let commentObj = {
            // Specific to Vocab
            orignal : orignal,
            improved : improved,
            explanation : explanation,
            // Commont Properties
            errorId : suggestionEl.id,
            commentType : 'grammer',
            commentedText : orignal,
            commentContent: `${orignal} -> ${improved} \n Explaination: ${explanation}`,
            commentCounter: globalCommentCounter
        }
        globalCommentCounter++;
        comments.push(commentObj);
    });

    return comments;
}

function getTextWithPlaceholders(element) {
    // Clone the element to avoid modifying the original HTML content
    const clonedElement = element.cloneNode(true);

    // Replace all span tags with a placeholder
    clonedElement.querySelectorAll('span').forEach((span, index) => {
        const placeholder = `<<SPAN_${index}>>`;
        span.replaceWith(placeholder);
    });

    // Return the text content with placeholders
    return clonedElement.textContent.replace(/\s+/g, ' ').trim();;
}

// Function to get the start index of the placeholder
function getSpanStartIndexInText(transcriptEl, span) {
    // Replace spans with placeholders and get the resulting text content
    const textWithPlaceholders = getTextWithPlaceholders(transcriptEl);
    console.log(textWithPlaceholders);
    // Generate the placeholder for the specific span
    const allSpans = Array.from(transcriptEl.querySelectorAll('span'));
    const spanIndex = allSpans.indexOf(span); // Get the index of the specific span
    const placeholder = `<<SPAN_${spanIndex}>>`;

    // Get the index of the placeholder in the text content
    const placeholderStartPos = textWithPlaceholders.indexOf(placeholder);

    return placeholderStartPos;
}

function getRawPronunComments(){
    const suggestions = document.querySelectorAll('#pronun-suggestions .pronun_error');
    let comments = [];
    suggestions.forEach(suggestionEl =>{
        let orignal = suggestionEl.querySelector('.sidepanel-orignal-pronun').getAttribute('data-text');
        let improved = suggestionEl.querySelector('.sidepanel-correct-pronun').innerText;
        let explanation = '';
        let commentObj = {
            // Specific to Vocab
            orignal : orignal,
            improved : improved,
            explanation : explanation,
            // Commont Properties
            errorId : suggestionEl.id,
            commentType : 'pronunciation',
            commentedText : orignal,
            commentContent: `${orignal} -> ${improved}`,
            commentCounter: globalCommentCounter
        }
        globalCommentCounter++;
        comments.push(commentObj);
    });

    return comments;
}


function convertVocabCommentstoDocxFormat(rawComments){
    // Use the passed user data
    console.log(writifyUserData);
    const authorName = writifyUserData.firstName + ' ' + writifyUserData.lastName || "Teacher";
    return rawComments.map((comment, index) => ({
        id: comment.commentCounter,
        author: authorName.trim() ?? 'Teacher', // Replaced "Teacher" with the user's name
        date: new Date(),
        children: [
            new docx.Paragraph({
                children: [
                    new docx.TextRun({
                        text: `${comment.orignal} -> ${comment.improved}`
                    })
                ]
            }),
            new docx.Paragraph({}),
            new docx.Paragraph({
                children: [
                    new docx.TextRun({
                        text: `${comment.explanation}`
                    })
                ]
            }),
        ]
    }));
}

function convertGrammerCommentstoDocxFormat(rawComments){
    // Use the passed user data
    const authorName = writifyUserData.firstName + ' ' + writifyUserData.lastName || "Teacher";
    return rawComments.map((comment, index) => ({
        id: comment.commentCounter,
        author: authorName.trim() ?? 'Teacher', // Replaced "Teacher" with the user's name
        date: new Date(),
        children: [
            new docx.Paragraph({
                children: [
                    new docx.TextRun({
                        text: `${comment.orignal} -> ${comment.improved}`
                    })
                ]
            }),
            new docx.Paragraph({}),
            new docx.Paragraph({
                children: [
                    new docx.TextRun({
                        text: `${comment.explanation}`
                    })
                ]
            }),
        ]
    }));
}

function convertPronunCommentstoDocxFormat(rawComments){
    // Use the passed user data
    const authorName = writifyUserData.firstName + ' ' + writifyUserData.lastName || "Teacher";
    return rawComments.map((comment, index) => ({
        id: comment.commentCounter,
        author: authorName.trim() ?? 'Teacher', // Replaced "Teacher" with the user's name
        date: new Date(),
        children: [
            new docx.Paragraph({
                children: [
                    new docx.TextRun({
                        text: `Pronunciation Error:`
                    })
                ]
            }),
            new docx.Paragraph({}),
            new docx.Paragraph({
                children: [
                    new docx.TextRun({
                        text: `${comment.orignal} -> ${comment.improved}`
                    })
                ]
            }),
        ]
    }));
}



async function exportDocument(saveBlob = true) {
    const customStyles = await fetchStylesXML();
    
    // Vocab comments
    let vocabRawComments = getRawVocabComments();
    let vocabComments = convertVocabCommentstoDocxFormat(vocabRawComments);
    // Grammer Comments
    let grammerRawComments = getRawGrammerComments();
    let grammerComments = convertGrammerCommentstoDocxFormat(grammerRawComments);
    // Pronun Comments
    let pronunRawComments = getRawPronunComments();
    let pronunComments = convertPronunCommentstoDocxFormat(pronunRawComments);


    let rawComments = [...vocabRawComments,...grammerRawComments,...pronunRawComments];
    let formattedComments = [...vocabComments,...grammerComments,...pronunComments];
    let sectionChildren = [
        ...createVocabSectionWithComments(vocabRawComments),
        ...createGrammerSectionWithComments(grammerRawComments),
        ...createPronunSectionWithComments(pronunRawComments),
        ...createFluencySection(),
    ];

    console.log(rawComments);
    console.log(formattedComments);
    const doc = new docx.Document({
        title: "Result", // Adjust as needed
        externalStyles: customStyles, // Use externalStyles instead of styles
        comments: {
            children: formattedComments
        },
        
        sections: [
            {
                properties: {},
                children: sectionChildren
            }
        ]
    });

    // Convert the document to a blob and save it
    generatedBlob = await docx.Packer.toBlob(doc);
    if(saveBlob == true){
        saveBlobAsDocx(generatedBlob);
    }
}

    async function getGeneratedBlob() {
        if(!generatedBlob){
            await exportDocument(false);
        }
        return generatedBlob;
    }

function createHeading(text) {
    const { Paragraph, TextRun, HeadingLevel } = docx;
    return new Paragraph({
        children: [new TextRun(text)],
        heading: HeadingLevel.HEADING_1
    });
}

function saveBlobAsDocx(blob) {
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = "Result.docx";
    document.body.appendChild(a);
    a.click();
    a.remove();
}

// Function to Export Document

function startDocumentExport(){
    console.log('Document Export Started');
    exportDocument().catch(error => console.error(error));
}
