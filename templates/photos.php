<?php /* @var $this ANS_BP_Photos */ ?>

<?php if ($this->user_can_edit()) : ?>
    <p>
        Можно добавить до <?= ANS_BP_Photos::MAX_PHOTO_NUM ?> изображений.
        Максимальный размер файлов: <?= round(wp_max_upload_size() / 1024 / 1024, 2) ?>MB.
        После загрузки выберите фото для установки аватара.

        <?php if ($this->is_avatar_exists()) : ?>
            <a class="ansavatar-delete" href="#">Удалить аватар</a>
        <?php endif; ?>
    </p>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="photos_upload">

        <input type="file" name="photos[]" accept="image/*" multiple>
        <input type="submit" value="Отправить">
    </form>

    <form id="photo_delete" method="post">
        <input type="hidden" name="action" value="photo_delete">
        <input type="hidden" name="url" value="" class="anscurimg">
    </form>

    <form id="avatar_update" method="post">
        <input type="hidden" name="action" value="avatar_update">
        <input type="hidden" name="url" value="" class="anscurimg">
        <input type="hidden" name="x" value="" class="anscropx">
        <input type="hidden" name="y" value="" class="anscropy">
        <input type="hidden" name="w" value="" class="anscropw">
        <input type="hidden" name="h" value="" class="anscroph">
    </form>

    <form id="avatar_delete" method="post">
        <input type="hidden" name="action" value="avatar_delete">
    </form>

    <?php bp_core_add_jquery_cropper(); ?>
<?php endif; ?>

<?php if ($thumbs) : ?>
    <div class="ansfull-cont">
        <img class="ansfull" src="">

        <?php if ($this->user_can_edit()) : ?>
            <div class="anscontrols">
                <a class="ansdelete" href="#">Удалить</a> |
                <a class="ansavatar-select" href="#">Использовать как аватар</a>
                <a class="ansavatar-upload" href="#">Применить и загрузить</a>
            </div>
        <?php endif; ?>
    </div>

    <div class="ansthumb-cont">
        <?php foreach ($thumbs as $thumb) : ?>
            <div><img class="ansthumb" src="<?= $base_url . basename($thumb) ?>"></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<style>
    /* Display */
    .ansthumb-cont {
        width: 100%;
        display: flex;
        margin-top: 0.5em;
    }
    .ansthumb-cont div {
        flex: 0 1 9.55%; /* (100% - 9 paddings * 0.5%) / 10 photos */
        padding-right: 0.5%;
    }
    .ansthumb-cont div:last-child { padding-right: 0; }
    .ansthumb-cont .ansthumb {
        display: block; /* To remove space under */
        max-width: calc(100% - 6px);
        padding: 2px;
        border: 1px solid #ddd;
        cursor: pointer;
    }
    .ansfull-cont {
        margin-top: 0.5em;
    }
    .ansfull {
        display: block;
        margin: 0 auto;
    }
    .ansfull[src=""] {
        display: none;
    }

    /* Admin */
    .ansfull[src=""] + .anscontrols,
    .ansavatar-upload,
    #photo_delete, 
    #avatar_update { 
        display: none; 
    }
    .anscontrols {
        text-align: center;
    }
    .jcrop-holder {
        margin: 0 auto;
    }

    /* Mobile */
    @media only screen and (max-width:600px) {
        .ansthumb-cont {
            flex-wrap: wrap;
        }
        .ansthumb-cont div {
            flex-basis: 19.6%; /* (100% - 4 paddings * 0.5%) / 5 photos */
            padding-right: 0.5%;
        }
        .ansthumb-cont div:nth-of-type(5n) { padding-right: 0; }
    }
</style>

<script>
    // Display
    jQuery(document).ready(function ($) {
        $('.ansthumb').click(function () {
            var img = $(this).attr('src').replace('-ansthumb', '-ansfull');

            $('.ansfull').fadeOut(100, function () {
                // Set full img src, if the thumb clicked the second time - hide the full img
                var src = $(this).attr('src') !== img ? img : '';
                $(this).attr('src', src);
            }).fadeIn(100);
        });
    });

    // Admin
    jQuery(document).ready(function ($) {
        var jcrop_api;

        $('.ansthumb').click(function () {
            destroy_jcrop();

            var img = $(this).attr('src').replace('-ansthumb', '-ansfull');
            $('.anscurimg').val(img);
        });

        $('.ansavatar-select').click(function () {
            destroy_jcrop();

            // Create a temp image to get its original unscaled size
            var tmp_img = new Image();

            tmp_img.onload = function () {
                var w = tmp_img.width;
                var h = tmp_img.height;
                var square = (w > h ? h : w) / 3; // Selection size, divide it to an arbitrary number

                // Initialize Jcrop
                $('.ansfull').Jcrop({
                    aspectRatio: 1,
                    setSelect: [w / 2 - square, h / 2 - square, w / 2 + square, h / 2 + square],
                    trueSize: [w, h],
                    onSelect: function (c) {
                        // Get coordinates
//                        console.log(c);

                        $('.anscropx').val(parseInt(c.x));
                        $('.anscropy').val(parseInt(c.y));
                        $('.anscropw').val(parseInt(c.w));
                        $('.anscroph').val(parseInt(c.w)); // we use again width here to get the exact square
                    }
                }, function () {
                    jcrop_api = this;
                });
            };

            tmp_img.src = $('.ansfull').attr('src'); // this must be done AFTER setting onload

            $('.ansavatar-select').hide();
            $('.ansavatar-upload').show();

            return false;
        });

        function destroy_jcrop() {
            if (typeof jcrop_api !== 'undefined') {
                jcrop_api.destroy();
            }

            $('.ansfull').removeAttr('style'); // jCrop doesn't clean its styling

            $('.ansavatar-select').show();
            $('.ansavatar-upload').hide();
        }

        // Delete image
        $('.ansdelete').click(function () {
            if (confirm('Вы уверены?')) {
                $('#photo_delete').submit();
            }

            return false;
        });

        // Update avatar
        $('.ansavatar-upload').click(function () {
            $('#avatar_update').submit();

            return false;
        });

        // Delete avatar
        $('.ansavatar-delete').click(function () {
            if (confirm('Вы уверены?')) {
                $('#avatar_delete').submit();
            }

            return false;
        });
    });
</script>