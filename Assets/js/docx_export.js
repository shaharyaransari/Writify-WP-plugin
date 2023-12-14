/**
 * Script Name: Docx Export
 * Version: 1.0.1
 * Last Updated: 07-12-2023
 * Author: bi1101
 * Description: Export the result page as docx files with comments.
 */
function extractRawCommentsFromHTML() {
    const vocabElements = document.querySelectorAll(".upgrade_vocab");
    const rawComments = [];

    vocabElements.forEach((element) => {
        const originalVocab = element.querySelector(".original-vocab").innerText;
        const improvedVocab = element.querySelector(".improved-vocab").innerText;
        const explanation = element.querySelector(".explanation").innerText;

        const commentData = {
            originalVocab: originalVocab,
            improvedVocab: improvedVocab,
            explanation: explanation
        };

        rawComments.push(commentData);
    });

    return rawComments;
}

function convertRawCommentsToDocxFormat(rawComments) {
    // Use the passed user data
    const authorName = writifyUserData.firstName + ' ' + writifyUserData.lastName || "Teacher";

    return rawComments.map((comment, index) => ({
        id: index,
        author: authorName, // Replaced "Teacher" with the user's name
        date: new Date(),
        children: [
            new docx.Paragraph({
                children: [
                    new docx.TextRun({
                        text: comment.originalVocab + " -> " + comment.improvedVocab
                    })
                ]
            }),
            new docx.Paragraph({}),
            new docx.Paragraph({
                children: [new docx.TextRun({ text: comment.explanation })]
            })
        ]
    }));
}

function createSectionsWithComments(rawComments) {
    const essayText = document.querySelector("#my-text").innerText;
    const essayPrompt = document
        .querySelector(".essay_prompt .elementor-widget-container")
        .innerText.trim();
    const essayParagraphs = essayText.split(/\\r?\\n/).map((p) => p.trimStart());
    const essayPromptParagraphs = essayPrompt
        .split(/\\r?\\n/)
        .map((p) => p.trimStart());
    const outputParagraphs = [];

    // Add the essay prompt paragraphs to the output
    for (let promptParagraph of essayPromptParagraphs) {
        if (promptParagraph.trim()) {
            // Check if paragraph is not just whitespace
            outputParagraphs.push(
                new docx.Paragraph({
                    children: [
                        new docx.TextRun({
                            text: promptParagraph,
                            bold: true
                        })
                    ]
                })
            );
        }
    }

    for (let paraText of essayParagraphs) {
        if (paraText.trim()) {
            // Check if paragraph is not just whitespace
            let currentPosition = 0;
            const paraChildren = [];
            let localCommentIndex = 0; // Reset for each paragraph

            while (localCommentIndex < rawComments.length) {
                const commentStartPos = paraText
                    .toLowerCase()
                    .indexOf(
                        rawComments[localCommentIndex].originalVocab.toLowerCase(),
                        currentPosition
                    );

                if (commentStartPos !== -1) {
                    // Add text before the comment
                    const preCommentText = paraText.slice(
                        currentPosition,
                        commentStartPos
                    );
                    paraChildren.push(new docx.TextRun(preCommentText));

                    // Add the comment
                    paraChildren.push(new docx.CommentRangeStart(localCommentIndex));
                    paraChildren.push(
                        new docx.TextRun(rawComments[localCommentIndex].originalVocab)
                    );
                    paraChildren.push(new docx.CommentRangeEnd(localCommentIndex));
                    paraChildren.push(
                        new docx.TextRun({
                            children: [new docx.CommentReference(localCommentIndex)]
                        })
                    );

                    currentPosition =
                        commentStartPos +
                        rawComments[localCommentIndex].originalVocab.length;

                    localCommentIndex++;
                } else {
                    console.warn(
                        `Skipped raw comment at index ${localCommentIndex} because it was not found in the essay text.`
                    );
                    // If no comment is found in the current paragraph, move on to the next comment
                    localCommentIndex++;
                    continue;
                }
            }

            // Add the remaining part of the paragraph
            const postCommentText = paraText.slice(currentPosition);
            paraChildren.push(new docx.TextRun(postCommentText));

            outputParagraphs.push(new docx.Paragraph({ children: paraChildren }));
        }
    }

    return outputParagraphs;
}

function createNormalSections(className) {
    const element = document.querySelector(
        `.${className} .elementor-widget-container .elementor-shortcode`
    );
    if (!element) {
        console.warn(`No element found with class name: ${className}`);
        return [];
    }

    const sections = [];
    element.childNodes.forEach((child) => {
        if (child.nodeType === 1) {
            // Check if the node is an element
            if (child.tagName === "P") {
                // For paragraph tags
                sections.push(htmlParagraphToDocx(child.outerHTML));
            } else if (child.tagName === "OL" || child.tagName === "UL") {
                // For ordered or unordered lists
                sections.push(...bulletPointsToDocx(child.outerHTML));
            }
        }
    });

    return sections;
}

function htmlParagraphToDocx(htmlContent) {
    // Convert the HTML string into a DOM element
    const tempDiv = document.createElement("div");
    tempDiv.innerHTML = htmlContent;

    const paragraph = tempDiv.querySelector("p");
    if (!paragraph) {
        console.warn("No paragraph element found in the provided HTML content.");
        return;
    }

    // Use processNodeForFormatting to handle child nodes
    const children = [];
    Array.from(paragraph.childNodes).forEach((child) => {
        children.push(...processNodeForFormatting(child));
    });

    return new docx.Paragraph({ children });
}

function processNodeForFormatting(node) {
    let textRuns = [];

    // Handle text nodes
    if (node.nodeType === 3) {
        // Node type 3 is a Text node
        textRuns.push(new docx.TextRun(node.nodeValue));
    }

    // Handle element nodes like <strong>, <em>, etc.
    else if (node.nodeType === 1) {
        // Node type 1 is an Element node
        const textContent = node.innerText;

        // Basic formatting options
        let formattingOptions = {};

        // Check the tag to determine formatting
        switch (node.tagName) {
            case "STRONG":
            case "B":
                formattingOptions.bold = true;
                break;
            case "EM":
            case "I":
                formattingOptions.italic = true;
                break;
            case "U":
                formattingOptions.underline = {
                    color: "auto",
                    type: docx.UnderlineType.SINGLE
                };
                break;
            // Add cases for other formatting tags as needed
        }

        // Check for nested formatting
        if (node.children.length > 0) {
            Array.from(node.childNodes).forEach((childNode) => {
                textRuns.push(...processNodeForFormatting(childNode));
            });
        } else {
            textRuns.push(
                new docx.TextRun({
                    text: textContent,
                    ...formattingOptions
                })
            );
        }
    }

    return textRuns;
}

function processList(list, level, paragraphs) {
    Array.from(list.children).forEach((item) => {
        paragraphs.push(createBulletPointParagraphs(item, level));

        // Process nested lists
        const nestedList = item.querySelector("ul, ol");
        if (nestedList) {
            processList(nestedList, level + 1, paragraphs);
        }
    });
}

function createBulletPointParagraphs(item, level) {
    let contentTextRuns = [];

    // Check if the item contains a paragraph element
    const paragraphElement = item.querySelector("p");

    if (paragraphElement) {
        contentTextRuns = processNodeForFormatting(paragraphElement);
    } else {
        Array.from(item.childNodes).forEach((childNode) => {
            contentTextRuns.push(...processNodeForFormatting(childNode));
        });
    }

    return new docx.Paragraph({
        children: contentTextRuns,
        bullet: {
            level: level
        }
    });
}

function bulletPointsToDocx(outerHTML) {
    const tempDiv = document.createElement("div");
    tempDiv.innerHTML = outerHTML;

    const docxItems = [];

    // Check whether the provided outerHTML is an ordered or unordered list and process it accordingly
    const listElement = tempDiv.querySelector("ol, ul");
    if (listElement) {
        processList(listElement, 0, docxItems);
    } else {
        console.warn(
            "Provided HTML does not contain a valid list element (ol or ul)."
        );
        return [];
    }

    return docxItems; // Ensure we return the docxItems
}

async function fetchStylesXML() {
    const response = await fetch('https://cdn.jsdelivr.net/gh/bi1101/Docx-Export-for-Writify/styles.xml');
    const xmlText = await response.text();
    return xmlText;
}

async function exportDocument() {
    const customStyles = await fetchStylesXML();

    const rawComments = extractRawCommentsFromHTML();
    const commentsForDocx = convertRawCommentsToDocxFormat(rawComments);

    // Extract headers from the document
    let arH = document.getElementsByClassName("ar_header")[0]?.innerText;
    let tH = document.getElementsByClassName("tr_header")[0]?.innerText;
    let ccH = document.getElementsByClassName("cc_header")[0]?.innerText;
    let lrH = document.getElementsByClassName("lr_header")[0]?.innerText;
    let grH = document.getElementsByClassName("gra_header")[0]?.innerText;
    let shH = document.getElementsByClassName("sample_header")[0]?.innerText;

    // Generating sections
    const sectionsChildren = [];
    sectionsChildren.push(...createSectionsWithComments(rawComments));

    // Add headers and their respective sections if they exist
    if (arH) {
        sectionsChildren.push(createHeaderParagraph(arH));
        sectionsChildren.push(...createNormalSections("ar_response"));
    }
    if (tH) {
        sectionsChildren.push(createHeaderParagraph(tH));
        sectionsChildren.push(...createNormalSections("tr_response"));
    }
    if (ccH) {
        sectionsChildren.push(createHeaderParagraph(ccH));
        sectionsChildren.push(...createNormalSections("cc_response"));
    }
    if (lrH) {
        sectionsChildren.push(createHeaderParagraph(lrH));
        sectionsChildren.push(...createNormalSections("lr_response"));
    }
    if (grH) {
        sectionsChildren.push(createHeaderParagraph(grH));
        sectionsChildren.push(...createNormalSections("gra_response"));
    }
    if (shH) {
        sectionsChildren.push(createHeaderParagraph(shH));
        sectionsChildren.push(...createNormalSections("sample_response"));
    }

    const doc = new docx.Document({
        title: "Result", // Adjust as needed
        externalStyles: customStyles, // Use externalStyles instead of styles
        comments: {
            children: commentsForDocx
        },
        sections: [
            {
                properties: {},
                children: sectionsChildren
            }
        ]
    });

    // Convert the document to a blob and save it
    docx.Packer.toBlob(doc).then((blob) => {
        saveBlobAsDocx(blob);
    });
}

function createHeaderParagraph(text) {
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

// Event Listener
document.addEventListener("DOMContentLoaded", function () {
    document.getElementById("export-docx").addEventListener("click", function () {
        exportDocument().catch(error => console.error(error));
    });
});
