document.addEventListener("DOMContentLoaded", () => {

    function loadLocal() {
        return JSON.parse(localStorage.getItem("projects")) || [];
    }

    function saveLocal(data) {
        localStorage.setItem("projects", JSON.stringify(data));
    }

    const form = document.getElementById("crud-form");
    const deleteForm = document.getElementById("delete-form");

    const infoOutput = document.getElementById("info-output");
    const errorOutput = document.getElementById("error-output");

    form.addEventListener("submit", (e) => {
        e.preventDefault();

        const title = document.getElementById("title").value;
        const img = document.getElementById("img").value;
        const alt = document.getElementById("alt").value;
        const desc = document.getElementById("desc").value;
        const link = document.getElementById("link").value;
        const index = document.getElementById("index").value;

        let projects = loadLocal();

        const newProject = { title, img, alt, desc, link };

        if (index === "") {
            // CREATE
            projects.push(newProject);
            infoOutput.textContent = "Project added!";
        } else {
            // UPDATE
            if (!projects[index]) {
                errorOutput.textContent = "Invalid index — no project exists.";
                return;
            }
            projects[index] = newProject;
            infoOutput.textContent = "Project updated!";
        }

        saveLocal(projects);
        form.reset();
    });

    // deleteForm.addEventListener("submit", (e) => {
    //     e.preventDefault();

    //     const index = document.getElementById("delete-index").value;
    //     let projects = loadLocal();

    //     if (!projects[index]) {
    //         errorOutput.textContent = "Invalid index — no project exists.";
    //         return;
    //     }

    //     projects.splice(index, 1);
    //     saveLocal(projects);

    //     infoOutput.textContent = "Project deleted!";
    //     deleteForm.reset();
    // });
    
    let pendingDeleteIndex = null;

    deleteForm.addEventListener("submit", (e) => {
        e.preventDefault();

        const index = document.getElementById("delete-index").value;
        let projects = loadLocal();

        if (!projects[index]) {
            errorOutput.textContent = "Invalid index — no project exists.";
            return;
        }

        pendingDeleteIndex = index;

        // Get project to preview
        const p = projects[index];

        const previewCard = document.getElementById("previewCard");

        // Remove old and replace with fresh component
        const newCard = document.createElement("project-card");
    
        newCard.setAttribute("title", p.title);
        newCard.setAttribute("img", p.img);
        newCard.setAttribute("alt", p.alt);
        newCard.setAttribute("desc", p.desc);
        newCard.setAttribute("link", p.link);

        // Replace node so connectedCallback() runs again
        previewCard.replaceWith(newCard);
        newCard.id = "previewCard";

        // Show modal
        document.getElementById("deleteModal").classList.remove("hidden");
    });

    document.getElementById("cancelDelete").addEventListener("click", () => {
    document.getElementById("deleteModal").classList.add("hidden");
    pendingDeleteIndex = null;
});

    document.getElementById("confirmDelete").addEventListener("click", () => {
        if (pendingDeleteIndex === null) return;

        let projects = loadLocal();
        projects.splice(pendingDeleteIndex, 1);
        saveLocal(projects);

        document.getElementById("deleteModal").classList.add("hidden");

        infoOutput.textContent = "Project deleted!";
        deleteForm.reset();

        pendingDeleteIndex = null;
    });


});
