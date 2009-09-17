/* Javascript magic */

$j = jQuery.noConflict();

var ktype_changed=false;
var ktype_color="";

function knbu_disable() {
 $j("#comment").fadeTo(0,0.33);
 $j("#comment")[0].disabled=true;
 $j("#submit")[0].disabled=true;
}

function knbu_enable() {
 $j("#comment")[0].disabled=false;
 $j("#comment").fadeTo("def",1.0).focus();
 $j("#submit")[0].disabled=false;
}

function scaffold_update(callback) {
 var ktype = $j("#knbu_real_ktype")[0].value;
 var link = $j(this);
 $j.post(link.attr("href"), {
   knbu_ktype_info: ktype
  }, function(data) {
   if ( ktype_color ) {
    $j("#knbu_heading,#knbu_popup_heading").removeClass(ktype_color);
   }
   $j("#knbu_heading,#knbu_popup_heading").text(data.name).addClass(data.color);
   ktype_color = data.color;
   $j("#knbu_checklist").html(data.checklist);
   $j("#knbu_popup_p").html(data.description);
   $j("#comment").text(data.phrases);
   $j("#comment").select();
   if (callback) callback();
  }, "json");
}

function scaffold_switch() {
  $j("#knbu_scaffold").fadeTo("slow",1.0);
}

function scaffold_open() {
 $j("#knbu_scaffold").slideDown("slow");
}

$j(document).ready(function() {
 /* Check whether KB is enabled; if not, do nothing */
 if ($j("#knbu").length==0) { return; }

 knbu_disable();

 /* Move selector to correct place */
 $j("#comment").parent().prev().append($j("#knbu"));
 $j("#knbu").show();

 $j("#knbu_select").click(function(e) {
  e.preventDefault();
  if ( ! $j("#knbu_ktype")[0].value ) {
   knbu_disable();
   if ( ! ktype_changed )
    $j("#knbu_ktype").fadeOut("fast").fadeIn("fast").fadeOut("fast").fadeIn("fast");
  } else {
   $j("#knbu_real_ktype").val($j("#knbu_ktype").val());
   $j("#knbu_ktype2").val($j("#knbu_ktype").val());

   scaffold_update(function() {
     scaffold_open();
     $j("#knbu_init").slideUp("slow");
     knbu_enable();
   });
  }
 });
 $j("#knbu_select2").click(function(e) {
  e.preventDefault();
  $j("#knbu_real_ktype").val($j("#knbu_ktype2").val());
  $j("#knbu_scaffold").fadeTo("slow",0.1,function(){scaffold_update(scaffold_switch);});
 });

 $j("#knbu_ktype").select(function(e) {
  ktype_changed=true;
 });

 $j("#knbu_popper").simpleDialog();

});