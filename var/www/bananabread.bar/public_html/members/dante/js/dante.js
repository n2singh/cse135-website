(function () {
  const gameBtn = document.getElementById("gameBtn");
  const gameOut = document.getElementById("gameOut");

  const movieBtn = document.getElementById("movieBtn");
  const movieOut = document.getElementById("movieOut");

  const games = [
    "Dead by Daylight",
    "Cyberpunk 2077",
    "Baldur's Gate 3",
    "Spiderman 2",
    "Marvel Rivals",
    "LEGO: Star Wars",
    "Midnight Suns",
    "Mortal Combat",
    "Dispatch",
    "Roblox"
  ];

  const movies = [
    "Bullet Train",
    "John Wick 4",
    "Despicable Me",
    "Spider-Man: Into the Spider-Verse",
    "The Barbie Movie",
    "Black Panther",
    "The Batman",
    "Star Wars: Revenge of the Sith",
    "Guardians of the Galaxy",
    "Oppenheimer"
  ];

  function pickRandom(arr) {
    return arr[Math.floor(Math.random() * arr.length)];
  }

  if (gameBtn && gameOut) {
    gameBtn.addEventListener("click", () => {
      gameOut.textContent = pickRandom(games);
    });
  }

  if (movieBtn && movieOut) {
    movieBtn.addEventListener("click", () => {
      movieOut.textContent = pickRandom(movies);
    });
  }
})();
