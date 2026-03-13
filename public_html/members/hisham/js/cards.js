class ProjectCard extends HTMLElement {
    constructor() {
        super();

        this.attachShadow({ mode: "open" });

        this.shadowRoot.innerHTML = `
            <style>
                :host {
                    display: block;
                    background: linear-gradient(to bottom, var(--bg-dark) 50%, var(--accent-color) 100%);
                    padding: 1rem;
                    border-radius: 12px;
                    box-shadow: 0 0 12px var(--accent-color);
                    max-width: 320px;
                    color: var(--text-color);
                    font-family: var(--font-body);
                    transition: transform 0.2s, box-shadow 0.3s;
                }
                :host(:hover) {
                    transform: translateY(-4px);
                    box-shadow: 0 0 24px var(--highlight-color);
                }
                h2 {
                    font-family: var(--font-heading);
                    font-size: 1.3rem;
                    margin-bottom: .4rem;
                }
                img {
                    width: 100%;
                    border-radius: 8px;
                    object-fit: cover;
                    margin-bottom: .6rem;
                    box-shadow: 0 0 10px var(--accent-color);
                }
                p {
                    font-size: .9rem;
                    margin-bottom: .6rem;
                    color: var(--text-muted);
                }
                a {
                    text-decoration: underline;
                    color: var(--bg-dark);
                }
                a:hover {
                    color: var(--highlight-color);
                }
            </style>

            <h2></h2>
            <picture>
                <img alt="">
            </picture>
            <p></p>
            <a target="_blank">Read more →</a>
        `;
    }

    connectedCallback() {
        this.shadowRoot.querySelector("h2").textContent =
            this.getAttribute("title") || "Untitled Project";

        const img = this.shadowRoot.querySelector("img");
        img.src = this.getAttribute("img") || "https://www.ntaskmanager.com/wp-content/uploads/2020/02/What-is-a-Project-1-scaled.jpg";
        img.alt = this.getAttribute("alt") || "Project image";

        this.shadowRoot.querySelector("p").textContent =
            this.getAttribute("desc") || "No description provided.";

        const link = this.shadowRoot.querySelector("a");
        link.href = this.getAttribute("link") || "#";
    }
}

customElements.define("project-card", ProjectCard);
