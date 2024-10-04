let pickerApiLoaded = false;
let oauthToken = sessionStorage.getItem('oauthToken');

document.addEventListener("DOMContentLoaded", function () {
    const exportButton = document.getElementById("export-google-docs");
    const fileSavedButton = document.getElementById("copy-result-link-trigger");

    if (exportButton) {
        exportButton.addEventListener("click", handleAuthClick);
    }

    if (oauthToken) {
        onApiLoad(); // Load Picker API if token is already available
    }
});

function exportDocumentToDrive(){
    handleAuthClick();
}

// Initialize Google API client
function initializeGapiClient() {
    return new Promise((resolve, reject) => {
        gapi.load('client', () => {
            gapi.client.init({
                apiKey: driveData.api_key,
                discoveryDocs: ["https://www.googleapis.com/discovery/v1/apis/drive/v3/rest"],
            }).then(() => {
                resolve(gapi);
            }).catch(error => {
                reject(error);
            });
        });
    });
}

// Create a token client
const tokenClient = google.accounts.oauth2.initTokenClient({
    client_id: driveData.client_id,
    scope: 'https://www.googleapis.com/auth/drive.file',
    callback: (tokenResponse) => {
        oauthToken = tokenResponse.access_token;
        sessionStorage.setItem('oauthToken', oauthToken); // Store token in session storage
        loadPickerApi();
    },
});

// Handle auth click
function handleAuthClick() {
    this.event.preventDefault();
    // Check We have the API Key and Client ID
    if (!driveData.api_key) {
        alert('API Key is missing');
        if (!driveData.client_id) {
            alert('Client ID is missing');
        }
    }
    const fileSavedButton = document.getElementById("copy-result-link-trigger");
    if (fileSavedButton) {
        fileSavedButton.style.display = 'block';
        fileSavedButton.textContent = 'Processing...';
        fileSavedButton.href = '#';
    }
    if (oauthToken) {
        loadPickerApi();
    } else {
        tokenClient.requestAccessToken();
    }
}

// Load the Picker API
function loadPickerApi() {
    if (!pickerApiLoaded) {
        gapi.load('picker', {'callback': onPickerApiLoad});
    } else {
        createPicker();
    }
}

// Picker API loaded
function onPickerApiLoad() {
    pickerApiLoaded = true;
    createPicker();
}

// Create and render a Picker object for picking a folder
function createPicker() {
    if (pickerApiLoaded && oauthToken) {
        const docsView = new google.picker.DocsView(google.picker.ViewId.FOLDERS)
            .setSelectFolderEnabled(true)
            .setEnableTeamDrives(false)  // Disable Team Drives
            .setOwnedByMe(true)  // Show only folders owned by the user
            .setIncludeFolders(true);  // Include folders in the view

        const picker = new google.picker.PickerBuilder()
            .setTitle('Select A Folder')
            .setOAuthToken(oauthToken)
            .addView(docsView)
            .setCallback(pickerCallback)
            .build();

        picker.setVisible(true);
    }
}

// Handle the results from the Picker
function pickerCallback(data) {
    const fileSavedButton = document.getElementById("copy-result-link-trigger");
    if (data.action === google.picker.Action.PICKED) {
        const folderId = data.docs[0].id;
        const fileNameInput = document.getElementById('file-name');
        const fileName = fileNameInput && fileNameInput.value ? fileNameInput.value : driveData.file_name;
        uploadFileToGoogleDrive(folderId, fileName);
    } else {
        if (fileSavedButton) {
            // fileSavedButton.style.display = 'none';
        }
    }
}

// Upload file to Google Drive
async function uploadFileToGoogleDrive(folderId, fileName) {
    const fileSavedButton = document.getElementById("copy-result-link-trigger");

    try {
        const gapi = await initializeGapiClient();

        const blob = await getGeneratedBlob(); // Assumes this function is globally accessible

        if (!blob) {
            alert('Something Went Wrong');
            console.error('No Blob found');
            throw new Error('No Blob found');
        }

        const fileContent = await blob.arrayBuffer();
        const file = new Blob([fileContent], { type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' });
        const metadata = {
            'name': fileName,
            'mimeType': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'parents': [folderId]
        };

        const form = new FormData();
        form.append('metadata', new Blob([JSON.stringify(metadata)], { type: 'application/json' }));
        form.append('file', file);

        const response = await fetch('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,webViewLink', {
            method: 'POST',
            headers: new Headers({ 'Authorization': 'Bearer ' + oauthToken }),
            body: form
        });

        const result = await response.json();
        console.log('File uploaded to Google Drive with ID:', result.id);

        if (fileSavedButton) {
            fileSavedButton.style.display = 'block';
            fileSavedButton.href = result.webViewLink;
            fileSavedButton.textContent = 'File Saved: See the file';
        }
    } catch (error) {
        console.error('Error uploading file:', error);
        alert('Something Went Wrong');
        if (fileSavedButton) {
            fileSavedButton.style.display = 'none';
        }
    }
}