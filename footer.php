</div> <!-- mainContent -->

</div> <!-- generalContent -->


<script type="text/javascript">
  $('.mask-phone').mask('+7 (999) 999-99-99');
</script>

<script type="text/javascript">
  $('.mask-time').mask('99:99');
</script>


<script>
  function noSubmitEnter(event) {
    if (event.keyCode == 13 || event.which == 13) {
      //alert('enter');
      event.preventDefault();
    }
  }
</script>


<script>
  $(document).on("input click change", "textarea", function () {
    $(this).outerHeight(38).outerHeight(this.scrollHeight + 3);
  });
</script>


<script>
  window.addEventListener("resize", function() {
    $('textarea').each(
      function(index){
        $(this).outerHeight(38).outerHeight(this.scrollHeight + 3);
      }
    );
  });
</script>


<script>
  document.addEventListener("DOMContentLoaded", function() {
    $('textarea').each(
      function(index){
        $(this).outerHeight(38).outerHeight(this.scrollHeight + 3);
      }
    );
  });
</script>


</form>
</body>
</html>
