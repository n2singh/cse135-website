(function () {
  const liveTime = document.getElementById("liveTime");
  if (liveTime) {
    liveTime.textContent = new Date().toString();
  }
})();
