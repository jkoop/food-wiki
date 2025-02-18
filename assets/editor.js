const files = [];
const addButton = document.querySelector("#add-button");
const form = document.querySelector("#edit-form");
const input = document.querySelector("#file-input");
const list = document.querySelector("#file-list");
const textarea = document.getElementsByTagName("textarea")[0];

addButton.addEventListener("click", () => {
	input.click();
});

input.addEventListener("input", (event) => {
	const input = event.target;

	for (const file of input.files) {
		files.push(file);
	}

	resetInput();
	renderList();
});

textarea.addEventListener("input", (event) => {
	const warning = document.getElementById("split-warning");
	if (event.target.value.split("\\n").filter((line) => line.match(/^# /)).length > 1) {
		warning.style.display = "inline";
	} else {
		warning.style.display = "none";
	}
});

function resetInput() {
	input.type = "text";
	input.type = "file";
}

function renderList() {
	list.innerHTML = "";

	for (const index in files) {
		const file = files[index];
		const li = document.createElement("li");
		li.innerText = file.name + " ";
		const button = document.createElement("button");
		button.addEventListener("click", deleteFile);
		button.dataset.fileIndex = index;
		button.innerText = "x";
		li.append(button);
		list.append(li);
	}

	updateHiddenFileInputs();
}

function updateHiddenFileInputs() {
	form.querySelectorAll("input.file-input").forEach((input) => input.remove());

	for (const file of files) {
		if (!(file instanceof File)) continue;
		const input = document.createElement("input");
		input.name = "images" + file.name;
		input.type = "file";
		const dataTransfer = new DataTransfer();
		dataTransfer.items.add(file);
		input.files = dataTransfer.files;
		input.style.display = "none";
		form.append(input);
	}
}

function deleteFile(event) {
	const button = event.target;
	const fileIndex = button.dataset.fileIndex;
	delete files[fileIndex];
	renderList();
}
