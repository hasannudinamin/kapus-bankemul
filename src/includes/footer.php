
</div>
</section>
<?php
if (isset($_SESSION['user_id'])) : // Only show footer if logged in
    /** @var $hide_footer */
    ?>
    <?php if (!isset($hide_footer) || $hide_footer !== true) : ?>
        <footer class="footer has-text-centered">
            <div class="content">
                <div class="has-text-grey">
      <p>
        Sistem Manajemen Resep Obat PRB<br>
        Puskesmas Bandarkedungmulyo
      </p>
                    
                </div>
            </div>
        </footer>
    <?php endif ?>
<?php endif ?>
</body>
</html>
