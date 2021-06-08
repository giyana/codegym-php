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

//投稿
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

//rtに値が存在する場合、RT元の投稿を取得し、コピー
//[rt]はRT元投稿のid
if (isset($_REQUEST['rt'])) {
    //RT元を取得する
    $retweet_original = $db->prepare('SELECT * FROM posts WHERE id = ?');
    $retweet_original->execute(array($_REQUEST['rt']));
    $retweet_original_ref = $retweet_original->fetch();
    //すでにRTされた投稿の数()
    $retweets_fitst_ref = $db->prepare('SELECT COUNT(id) FROM posts WHERE retweet_post_id <> "0" AND member_id = ?');
    $retweets_fitst_ref->execute(array($member['id']));
    $retweet_first_ref = $retweets_fitst_ref->fetch();
    if (isset($retweet_first_ref["COUNT(id)"])) {
        //RT元を参照して、投稿(ボタン押すの1回目)
        //member_idはRTした人のidで登録
        $retweet_post = $db->prepare('INSERT INTO posts SET message=?, member_id=?, reply_post_id=?, retweet_post_id=?, created=NOW()');
        $retweet_post->execute(array(
            $retweet_original_ref['message'],
            $member['id'],
            $retweet_original_ref['reply_post_id'],
            $_REQUEST['rt']
        ));
    } else {
        //RT済の場合、削除(ボタン押すの2回目)
        $retweet_del = $db->prepare('DELETE FROM posts WHERE id = ?');
        $retweet_del->execute(array($_REQUEST['rt']));
    }
    header('Location: index.php');
    exit();
}

//ボタンを押すとfavoritesテーブルにデータ挿入＆削除
if (isset($_POST['favorite'])) {
    //すでにいいねしてる場合削除
    if (isset($_POST['post_id_fav_del'])) {
        $favorites_del = $db->prepare('DELETE FROM favorites WHERE member_id = ? AND post_id = ?');
        $favorites_del->execute(array(
            $member['id'],
            (int)$_POST['post_id_fav_del']
        ));
    } else {
        //以前にいいねなしの場合データ挿入
        //RTされた投稿に対してのいいねは、post_idをRT元のものにする
        if ($_POST['origin_rt_fav'] === 0) {
            $favorites_rt = $db->prepare('INSERT INTO favorites SET member_id = ?, post_id = ?, created=NOW()');
            $favorites_rt->execute(array(
                $member['id'],
                $_POST['origin_rt_fav']
            ));
        } else {
            $favorites = $db->prepare('INSERT INTO favorites SET member_id = ?, post_id = ?, created=NOW()');
            $favorites->execute(array(
                $member['id'],
                $_POST['post_id_fav']
            ));
        }
    }
    header('Location: index.php');
    exit();
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
                    <?php var_dump($post['id']) ?>
                    <!-- RTした投稿の場合、名前表示 -->
                    <p><?php
                        //RTした人の名前を取得したい
                        $members_ref = $db->prepare('SELECT * FROM members INNER JOIN posts ON members.id = posts.member_id WHERE posts.id = ?');
                        $members_ref->execute(array($post['id']));
                        $member_ref = $members_ref->fetch();
                        if ($post['retweet_post_id'] != 0) {
                            print h($member_ref["name"] . "さんがリツイートしました");
                        }
                        ?></p>
                    <p><?php echo makeLink(h($post['message'])); ?><span class="name">（<?php echo h($post['name']); ?>）</span>[<a href="index.php?res=<?php echo h($post['id']); ?>">Re</a>]</p>

                    <p class="day">
                        <!-- 課題：リツイートといいね機能の実装 -->
                        <?php
                        //RTされていない投稿の総いいね数を取得
                        $favorite_counts = $db->prepare('SELECT COUNT(id) FROM favorites WHERE post_id=?');
                        $favorite_counts->bindParam(1, $post['id']);
                        $favorite_counts->execute();
                        $favorite_count = $favorite_counts->fetch();

                        //ログインしている人がいいねしているかどうか 0の場合いいねなし
                        $favorites_ref = $db->prepare('SELECT COUNT(*) FROM favorites WHERE member_id = ? AND post_id = ?');
                        $favorites_ref->execute(array(
                            $member['id'],
                            $post['id']
                        ));
                        $favorite_ref = $favorites_ref->fetch();

                        //RTされた投稿にいいねがついているかどうか
                        //rt_post_id=0かどうか判定 RTされた投稿は$rt_fav_refに0が出力
                        $rt_favs_ref = $db->prepare('SELECT * FROM posts WHERE id = ? AND retweet_post_id = 0');
                        $rt_favs_ref->execute(array($post['id']));
                        $rt_fav_ref = $rt_favs_ref->fetch();
                        var_dump((int)$rt_fav_ref);

                        //RT元の情報を取得 元ツイートの場合0を出力
                        $origin_rts = $db->prepare('SELECT * FROM posts WHERE id = ?');
                        $origin_rts->execute(array(($post['id'])));
                        $origin_rt = $origin_rts->fetch();
                        $origin_rt_fav = (int)$origin_rt["retweet_post_id"];
                        var_dump($origin_rt_fav);
                        //↑これはrt元のpost_id

                        //RTされた投稿の総いいね数取得
                        $favorite_counts_rt = $db->prepare('SELECT COUNT(*) FROM favorites WHERE post_id=?');
                        $favorite_counts_rt->bindValue(1, $origin_rt_fav);
                        $favorite_counts_rt->execute();
                        $favorite_count_rt = $favorite_counts_rt->fetch();
                        //var_dump($favorite_count_rt);

                        //各投稿のRT数を取得
                        $retweet_counts = $db->prepare('SELECT COUNT(*) FROM posts WHERE retweet_post_id = ?');
                        $retweet_counts->execute(array($post['id']));
                        $retweet_count = $retweet_counts->fetch();
                        //var_dump($retweet_count["COUNT(*)"]);

                        //ログインしている人がRTしているかどうか 元RTの場合0を出力
                        $retweets_ref = $db->prepare('SELECT * FROM posts WHERE member_id = ? AND retweet_post_id = ? ');
                        $retweets_ref->execute(array($member['id'], $post['id']));
                        $retweet_ref = $retweets_ref->fetch();
                        //var_dump((int)$retweet_ref["retweet_post_id"]);
                        ?>

                        <!-- RTフォーム -->
                        <!-- RTボタンを押すと、post_idをURLパラメーターで受け渡す -->
                        <!--ログインしている人が各投稿RTしているかどうかで色変え条件分岐 -->
                        <?php if ((int)$retweet_ref["retweet_post_id"] === 0) : ?>
                            <span class="retweet">
                                <a href="index.php?rt=<?php echo h($post['id']); ?>"><img class="retweet-image" src="images/retweet-solid-gray.svg"></a>
                                <span style="color:gray;"><?php echo h((int)$retweet_count["COUNT(*)"]); ?></span>
                            </span>
                        <?php else : ?>
                            <span class="retweet">
                                <a href="index.php?rt=<?php echo h($post['id']); ?>"><img class="retweet-image" src="images/retweet-solid-blue.svg"></a>
                                <span style="color:blue;"><?php echo h((int)$retweet_count["COUNT(*)"]); ?></span>
                            </span>
                        <?php endif; ?>


                        <!-- いいねフォーム -->


                        <!-- ログインしている人が各投稿をいいねしているかどうかで色替え条件分岐
                        各投稿をいいねしているかではなく、-->
                        <?php if ((int)$favorite_ref["COUNT(*)"] === 0) : ?>
                            <span class="favorite">
                                <!-- いいねボタンを押したときのフォーム -->
                                <form action="" method="post">
                                    <button type="submit" name="favorite"><img class="favorite-image" src="images/heart-solid-gray.svg"></button>
                                    <input type="hidden" name="post_id_fav" value="<?php print h($post['id']); ?>">
                                    <input type="hidden" name="origin_rt_fav" value="<?php print h($origin_rt_fav); ?>">
                                    <!-- 総いいね数表示 -->
                                </form>
                                <span style="color:gray;"><?php
                                                            //RTされた投稿であれば、RT元のいいね数を表示
                                                            //RT元のいいね数
                                                            if ((int)$rt_fav_ref === 0) {
                                                                echo h((int)$favorite_count_rt["COUNT(*)"]);
                                                            } else {
                                                                echo h((int)$favorite_count['COUNT(id)']);
                                                            }
                                                            ?>
                                </span>
                            </span>
                        <?php else : ?>
                            <span class="favorite">
                                <form action="" method="post">
                                    <button type="submit" name="favorite"><img class="favorite-image" src="images/heart-solid-red.svg"></button>
                                    <input type="hidden" name="post_id_fav_del" value="<?php print h($post['id']); ?>">
                                    <input type="hidden" name="origin_rt_fav" value="<?php print h($origin_rt_fav); ?>">
                                </form>
                                <span style="color:red;"><?php
                                                            //RTされた投稿であれば、RT元のいいね数を表示
                                                            //RT元のいいね数
                                                            if ((int)$rt_fav_ref === 0) {
                                                                echo h((int)$favorite_count_rt);
                                                            } else {
                                                                echo h($favorite_count['COUNT(id)']);
                                                            }
                                                            ?>
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
