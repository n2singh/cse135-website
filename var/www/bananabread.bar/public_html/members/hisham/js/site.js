document.addEventListener("DOMContentLoaded", () => {
    const root = document.documentElement;
    const select = document.getElementById("theme-select");

    if (!select) return;

    select.hidden = false;

    // Load saved theme or system preference
    const savedTheme = localStorage.getItem("theme");
    const prefersDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
    const initial = savedTheme || (prefersDark ? "dark" : "light");

    applyTheme(initial);
    select.value = initial;

    select.addEventListener("change", () => {
        const newTheme = select.value;

        if (document.startViewTransition) {
            document.startViewTransition(() => applyTheme(newTheme));
        } else {
            applyTheme(newTheme);
        }
    });

    function applyTheme(theme) {
        root.dataset.theme = theme;
        localStorage.setItem("theme", theme);
    }

    //const localData = [
    //     {
    //         title:"Peronsal Portfolio Website",
    //         img:"/images/projects/myPortfolio.png",
    //         alt:"Homepage preview of my UCSD themed site",
    //         desc:"This website itself! Built using semantic HTML, CSS variables, theming, and JavaScript components.",
    //         link:"index.html"
    //     },
    //     {
    //         title:"Internship: AI Agent Log Analysis",
    //         img:"/images/projects/ai_logs.png",
    //         alt:"Screenshot of AI agent logs",
    //         desc: "Analyzed open-source models and extracted agent logging patterns during my SkillWorld internship.",
    //         link:"project_details/agent_logging_project.html"
    //     },
    //     {
    //         title:"Custom Computer Graphics Renderer",
    //         img:"/images/projects/Computer_Graphics_outputs/donut.png",
    //         alt:"Rendered image of a donut model with lighting effects",
    //         desc:"Extended a CPU-based graphics framework with rasterization, lighting, and Blender model rendering.",
    //         link:"project_details/computer_graphics_project.html"
    //     }
    // ];
    
    // if (!localStorage.getItem("projects")) {
    //     localStorage.setItem("projects", JSON.stringify(localData));
    // }
    // function loadProjects(data) {
    //     const container = document.getElementById("projects");
    //     container.innerHTML = "";
    //     data.forEach(project => {
    //         const card = document.createElement("project-card");
    //         card.setAttribute("title", project.title);
    //         card.setAttribute("img", project.img);
    //         card.setAttribute("alt", project.alt);
    //         card.setAttribute("desc", project.desc);
    //         card.setAttribute("link", project.link);
    //         container.appendChild(card);
    //     });
    // }
    // document.getElementById("load-local").addEventListener("click", () => {
    //     const stored = JSON.parse(localStorage.getItem("projects")) || [];
    //     loadProjects(stored);
    // });
    // document.getElementById("reset-data").addEventListener("click", () => {
    //     localStorage.setItem("projects", JSON.stringify(localData));
    //     alert("Default projects restored to original state! Refresh the page to see changes.");
    // });
    // document.getElementById("load-remote").addEventListener("click", async () => {
    //     try {
    //         const response = await fetch("https://api.jsonbin.io/v3/b/6932562aae596e708f848bc8");
    //         if (!response.ok) throw new Error("Response status: " + response.status);
    //         const remoteData = await response.json();
    //         loadProjects(remoteData.record);
    //     } catch (error) {
    //         console.error("Failed to load remote projects:", error);
    //     }
    // });

    const container = document.getElementById("projects");

    container.addEventListener("click", (e) => {
        const card = e.target.closest("project-card");
        if (!card || !container.contains(card)) return;
        const clickedLink = e.target.closest("a");
        if (clickedLink) return;
        const link = card.getAttribute("link");
        if (link && link !== "#") {
            window.open(link, "_blank");
        }
    });

});