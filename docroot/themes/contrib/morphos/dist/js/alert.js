((t,r)=>{t.behaviors.alert={attach:l=>{r("alert",".alert__close",l).forEach(e=>{const a=e.closest(".alert");e.addEventListener("click",c=>{c.preventDefault(),a&&a.remove()})})}}})(Drupal,once);
