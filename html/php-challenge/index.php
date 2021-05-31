<?php
session_start();
require('dbconnect.php');

if (isset($_SESSION['id']) && $_SESSION['time'] + 3600 > time()) {
    // ログインしている
    $_SESSION['time'] = time();

    $members = $db->prepare('SELECT * FROM members WHERE id=?');
    $members->execute(array($_SESSION['id']));
    $member = $members->fetch();
} else {
    // ログインしていない
    header('Location: login.php');
    exit();
}

//SQLに渡す前に変数でreply_post_id空文字をどう処理するか定義(空文字送信するとSQLに反映されないため)
if (empty($_POST['reply_post_id'])) {
    $reply_post_id = "0";
} else {
    $reply_post_id = $_POST['reply_post_id'];
}

//初投稿時+返信時
if (!empty($_POST)) {
    if ($_POST['message'] != '') {
        $message = $db->prepare('INSERT INTO posts SET member_id=?, message=?, reply_post_id= ' . $reply_post_id . ', retweet_post_id=0, created=NOW()');
        $message->execute(array(
            $member['id'],
            $_POST['message'],
        ));
        header('Location: index.php');
        exit();
    }
}

// 投稿を取得する
$page = $_REQUEST['page'];
if ($page == '') {
    $page = 1;
}
$page = max($page, 1);

// 最終ページを取得する
$counts = $db->query('SELECT COUNT(*) AS cnt FROM posts');
$cnt = $counts->fetch();
$maxPage = ceil($cnt['cnt'] / 5);
$page = min($page, $maxPage);

$start = ($page - 1) * 5;
$start = max(0, $start);

$posts = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id ORDER BY p.created DESC LIMIT ?, 5');
$posts->bindParam(1, $start, PDO::PARAM_INT);
$posts->execute();

// 返信の場合
if (isset($_REQUEST['res'])) {
    $response = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id AND p.id=? ORDER BY p.created DESC');
    $response->execute(array($_REQUEST['res']));

    $table = $response->fetch();
    $message = '@' . $table['name'] . ' ' . $table['message'];
}

// htmlspecialcharsのショートカット
function h($value)
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// 本文内のURLにリンクを設定します
function makeLink($value)
{
    return mb_ereg_replace("(https?)(://[[:alnum:]\+\$\;\?\.%,!#~*/:@&=_-]+)", '<a href="\1\2">\1\2</a>', $value);
}

//ボタンを押すとRT数+1
if (isset($_POST['retweet_count'])) {
    $retweet_count_up = $db->prepare('UPDATE posts SET retweet_count = retweet_count + 1 WHERE id=?');
    $retweet_count_up->execute(array($_POST['post_id_rt']));
}
//ボタンを押すとfavoritesテーブルにデータ挿入＆削除
if (isset($_POST['favorite'])) {
    //すでにいいねしてる場合削除
    if(isset($_POST['post_id_fav_del'])){
    $favorites_del = $db->prepare('DELETE FROM favorites WHERE member_id = ? AND post_id = ?');
    $favorites_del->execute(array(
        $member['id'],
        $_POST['post_id_fav_del']
    ));
}else{
    //以前にいいねなしの場合データ挿入
    $favorites = $db->prepare('INSERT INTO favorites SET member_id = ?, post_id = ?, created=NOW()');
    $favorites->execute(array(
        $member['id'],
        $_POST['post_id_fav']
    ));
}
}

?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>ひとこと掲示板</title>

    <link rel="stylesheet" href="style.css" />
</head>

<body>
    <div id="wrap">
        <div id="head">
            <h1>ひとこと掲示板</h1>
        </div>
        <div id="content">
            <div style="text-align: right"><a href="logout.php">ログアウト</a></div>
            <form action="" method="post">
                <dl>
                    <dt><?php echo h($member['name']); ?>さん、メッセージをどうぞ</dt>
                    <dd>
                        <textarea name="message" cols="50" rows="5"><?php echo h($message); ?></textarea>
                        <input type="hidden" name="reply_post_id" value="<?php echo h($_REQUEST['res']); ?>" />
                    </dd>
                </dl>
                <div>
                    <p>
                        <input type="submit" value="投稿する" />
                    </p>
                </div>
            </form>

            <?php
            foreach ($posts as $post) :
            ?>
                <div class="msg">
                    <img src="member_picture/<?php echo h($post['picture']); ?>" width="48" height="48" alt="<?php echo h($post['name']); ?>" />
                    <p><?php echo makeLink(h($post['message'])); ?><span class="name">（<?php echo h($post['name']); ?>）</span>[<a href="index.php?res=<?php echo h($post['id']); ?>">Re</a>]</p>

                    <p class="day">
                        <!-- 課題：リツイートといいね機能の実装 -->
                        <?php
                        //各投稿の総いいね数を取得
                        $favorite_counts = $db->prepare('SELECT COUNT(id) FROM favorites WHERE post_id=?');
                        $favorite_counts->bindParam(1, $post['id']);
                        $favorite_counts->execute();
                        $favorite_count = $favorite_counts->fetch();
                        ?>
                    <!-- RTフォーム -->
                        <!-- 各投稿のRT数が0かどうかで色変え条件分岐 -->
                        <?php if($post['retweet_count'] == 0) : ?>
                            <span class="retweet">
                                <form action="" method="post">
                                    <button type="submit" name="retweet_count"><img class="retweet-image" src="images/retweet-solid-gray.svg"></button>
                                    <input type="hidden" name="post_id_rt" value="<?php print h($post['id']); ?>">
                                </form>
                                <span style="color:gray;"><?php echo h($post['retweet_count']) ?></span>
                            </span>
                        <?php else : ?>
                            <span class="retweet">
                                <form action="" method="post">
                                    <button type="submit" name="retweet_count"><img class="retweet-image" src="images/retweet-solid-blue.svg"></button>
                                    <input type="hidden" name="post_id_rt" value="<?php print h($post['id']); ?>">
                                </form>
                                <span style="color:blue;"><?php echo h($post['retweet_count']) ?></span>
                        <?php endif; ?>

                    <!-- いいねフォーム -->
                        <!-- 各投稿の総いいね数が0かどうかで色替え条件分岐 -->
                        <?php if ($favorite_count['COUNT(id)'] == 0) : ?>
                            <span class="favorite">
                                <!-- いいねボタンを押したときのフォーム -->
                                <form action="" method="post">
                                    <button type="submit" name="favorite"><img class="favorite-image" src="images/heart-solid-gray.svg"></button>
                                    <input type="hidden" name="post_id_fav" value="<?php print h($post['id']); ?>">
                                <!-- 総いいね数表示 -->
                                </form>
                                <span style="color:gray;"><?php echo h($favorite_count['COUNT(id)']) ?>
                                </span>
                            </span>
                        <?php else : ?>
                            <span class="favorite">
                                <form action="" method="post">
                                    <button type="submit" name="favorite"><img class="favorite-image" src="images/heart-solid-red.svg"></button>
                                    <input type="hidden" name="post_id_fav_del" value="<?php print h($post['id']); ?>">
                                </form>
                                <span style="color:red;"><?php echo h($favorite_count['COUNT(id)']) ?>
                                </span>
                            </span>
                        <?php endif; ?>

                        <a href="view.php?id=<?php echo h($post['id']); ?>"><?php echo h($post['created']); ?></a>
                        <?php
                        if ($post['reply_post_id'] > 0) :
                        ?><a href="view.php?id=<?php echo h($post['reply_post_id']); ?>">
                                返信元のメッセージ</a>
                        <?php
                        endif;
                        ?>
                        <?php
                        if ($_SESSION['id'] == $post['member_id']) :
                        ?>
                            [<a href="delete.php?id=<?php echo h($post['id']); ?>" style="color: #F33;">削除</a>]
                        <?php
                        endif;
                        ?>
                    </p>
                </div>
            <?php
            endforeach;
            ?>

            <ul class="paging">
                <?php
                if ($page > 1) {
                ?>
                    <li><a href="index.php?page=<?php print($page - 1); ?>">前のページへ</a></li>
                <?php
                } else {
                ?>
                    <li>前のページへ</li>
                <?php
                }
                ?>
                <?php
                if ($page < $maxPage) {
                ?>
                    <li><a href="index.php?page=<?php print($page + 1); ?>">次のページへ</a></li>
                <?php
                } else {
                ?>
                    <li>次のページへ</li>
                <?php
                }
                ?>
            </ul>
        </div>
    </div>
</body>

</html>
