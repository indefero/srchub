$("textarea").keydown(function(e) {
  var $this, end, start;
  if (e.keyCode === 9) {
    start = this.selectionStart;
    end = this.selectionEnd;
    $this = $(this);
    $this.val($this.val().substring(0, start) + "\t" + $this.val().substring(end));
    this.selectionStart = this.selectionEnd = start + 1;
    return false;
  }
});