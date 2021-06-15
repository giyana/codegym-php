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

//ログインしている人が各投稿をRTしているかどうか判定する変数
$menber_rts = $db->prepare('SELECT COUNT(*) FROM posts WHERE member_id = ? AND retweet_post_id = ?');
$menber_rts->execute(array(
    $member['id'],
    $_REQUEST['rt']
));
$menber_rt = $menber_rts->fetch();
if ((int)$menber_rt["COUNT(*)"] === 0) {
    $has_menber_rt = false;
} else {
    $has_menber_rt = true;
}
//var_dump($has_menber_rt);

//rtに値が存在する場合、RT元の投稿を取得し、コピー
//[rt]はRTボタンが置かれている投稿id
if (isset($_REQUEST['rt'])) {
    //RT元を取得する
    $retweet_original = $db->prepare('SELECT * FROM posts WHERE id = ?');
    $retweet_original->execute(array($_REQUEST['rt']));
    $retweet_original_ref = $retweet_original->fetch();
    //すでにRTされているか
    //member_idはRTした人のidで登録
    //var_dump($_GET["rt_fav_ref"]);
    //RTされていない投稿は rt_fav_ref = 1 
    if ((int)$_GET["rt_fav_ref"]  === 1) {
        //var_dump("RTされていない投稿");
        $retweet_post = $db->prepare('INSERT INTO posts SET message=?, member_id=?, reply_post_id=?, retweet_post_id=?, created=NOW()');
        $retweet_post->execute(array(
            $retweet_original_ref['message'],
            $member['id'],
            $retweet_original_ref['reply_post_id'],
            $_REQUEST['rt']
        ));
    } else {


        //RT削除かRT投稿をRTか条件分岐(この時点でretweet_post_id != 0)
        ////ログインしている人がRTしているかどうか trueで過去にRTあり
        if ($has_menber_rt) {
            //var_dump("RT削除");
            $retweet_del = $db->prepare('DELETE FROM posts WHERE retweet_post_id = ?');
            $retweet_del->execute(array($_REQUEST['rt']));
        } else {
            //var_dump("RTされている投稿をRT");
            $re_retweet_post = $db->prepare('INSERT INTO posts SET message=?, member_id=?, reply_post_id=?, retweet_post_id=?, created=NOW()');
            $re_retweet_post->execute(array(
                $retweet_original_ref['message'],
                $member['id'],
                $retweet_original_ref['reply_post_id'],
                $_POST['origin_rt_fav']
            ));
        }
    }
    header('Location: index.php');
    exit();
}

//ボタンを押すとfavoritesテーブルにデータ挿入＆削除
//var_dump((int)($_POST['origin_rt_fav']));
if (isset($_POST['favorite'])) {
    //すでにいいねしてる場合削除
    if (isset($_POST['post_id_fav_del'])) {
        $favorites_del = $db->prepare('DELETE FROM favorites WHERE member_id = ? AND post_id = ?');
        $favorites_del->execute(array(
            $member['id'],
            (int)$_POST['post_id_fav_del']
        ));
    } else {
        //RTされた投稿に対してのいいねは、post_idをRT元のものにする
        //var_dump((int)$_POST["rt_fav_refs"]);
        //$retweet_ref["retweet_post_id"] = 0はRTされている
        if ((int)$_POST["rt_fav_refs"] === 0) {
            //RTされた投稿に対するいいね
            //var_dump($_POST['post_id_fav']);
            $favorites_rt = $db->prepare('INSERT INTO favorites SET member_id = ?, post_id = ?, created=NOW()');
            $favorites_rt->execute(array(
                $member['id'],
                $_POST['origin_rt_fav']
                //↑RT元の投稿idを参照したい
            ));
        } else {
            //RTされている投稿
            //var_dump($_POST["post_id"]);
            $favorites = $db->prepare('INSERT INTO favorites SET member_id = ?, post_id = ?, created=NOW()');
            $favorites->execute(array(
                $member['id'],
                $_POST["post_id"]
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
                    <!-- <?php var_dump($post['id']) ?> -->
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

                        //(RTされた投稿にいいねがついているかどうか判定用）
                        //rt_post_id=0かどうか判定 RTされた投稿は$rt_fav_refに0が出力
                        $rt_favs_ref = $db->prepare('SELECT * FROM posts WHERE id = ? AND retweet_post_id = 0');
                        $rt_favs_ref->execute(array($post['id']));
                        $rt_fav_ref = $rt_favs_ref->fetch();
                        //print('$rt_fav_ref');
                        //var_dump((int)$rt_fav_ref);

                        //RT元の情報を取得 元ツイートの場合0を出力
                        $origin_rts = $db->prepare('SELECT * FROM posts WHERE id = ?');
                        $origin_rts->execute(array(($post['id'])));
                        $origin_rt = $origin_rts->fetch();
                        $origin_rt_fav = (int)$origin_rt["retweet_post_id"];
                        //print('$origin_rt_fav');
                        //var_dump($origin_rt_fav);
                        //↑これはrt元のpost_id
                        ?>

                        <!-- RTフォーム -->
                        <!-- RTボタンを押すと、post_idをURLパラメーターで受け渡す -->
                        <!--ログインしている人が各投稿RTしているか、どうかで色変え条件分岐 -->
                    <div class="retweet">
                        <?php
                        //ログインしている人がRTしているかどうか 元RTの場合0を出力
                        $retweets_ref = $db->prepare('SELECT * FROM posts WHERE member_id = ? AND retweet_post_id = ? ');
                        $retweets_ref->execute(array($member['id'], $post['id']));
                        $retweet_ref = $retweets_ref->fetch();
                        //print "retweet_ref";
                        //var_dump((int)$retweet_ref);
                        //var_dump((int)$retweet_ref["retweet_post_id"]);

                        //ログインしている人がいいねしているかどうか 0の場合いいねなし
                        $favorites_ref = $db->prepare('SELECT COUNT(*) FROM favorites WHERE member_id = ? AND post_id = ?');
                        $favorites_ref->execute(array(
                            $member['id'],
                            $post['id']
                        ));
                        $favorite_ref = $favorites_ref->fetch();

                        //RT数を取得
                        $rt_counts = $db->prepare('SELECT COUNT(*) FROM posts WHERE retweet_post_id = ?');
                        var_dump((int)$post['retweet_post_id']);
                        if ((int)$post["retweet_post_id"] === 0) {
                            print "a";
                            var_dump((int)$post["id"]);
                            $rt_counts->execute(array($post['id']));
                        } else {
                            print "b";
                            $rt_counts->bindValue(1, $origin_rt_fav);
                            $rt_counts->execute();
                        }
                        $rt_count = $rt_counts->fetch(PDO::FETCH_ASSOC);
                        //var_dump($rt_count["COUNT(*)"]);

                        
                        if ((int)$retweet_ref["retweet_post_id"] === 0) : ?>
                            <a href="index.php?rt=<?php echo h($post['id']); ?>&rt_fav_ref=<?php echo h((int)$rt_fav_ref) ?>&retweet_ref=<?php echo h((int)$retweet_ref) ?>">
                                <img class="retweet-image" src="images/retweet-solid-gray.svg"></a>
                            <span style="color:gray;">
                            <?php else : ?>
                                <a href="index.php?rt=<?php echo h($post['id']); ?>"><img class="retweet-image" src="images/retweet-solid-blue.svg"></a>
                                <span style="color:blue;">
                                <?php endif; ?>
                                <?php
                                echo h((int)$rt_count["COUNT(*)"]);
                                ?>
                                </span>
                    </div>

                    <!-- いいねフォーム -->
                    <!-- ログインしている人が各投稿をいいねしているかどうかで色替え条件分岐
                        各投稿をいいねしていいるか、過去RTをいいねしているか-->
                    <div class="favorite">
                        <?php
                        //RTされていない投稿の総いいね数を取得
                        $favorite_counts = $db->prepare('SELECT COUNT(id) FROM favorites WHERE post_id=?');
                        $favorite_counts->bindParam(1, $post['id']);
                        $favorite_counts->execute();
                        $favorite_count = $favorite_counts->fetch();

                        //RTされた投稿の総いいね数取得
                        $favorite_counts_rt = $db->prepare('SELECT COUNT(*) FROM favorites WHERE post_id=?');
                        $favorite_counts_rt->bindValue(1, $origin_rt_fav);
                        $favorite_counts_rt->execute();
                        $favorite_count_rt = $favorite_counts_rt->fetch();
                        //var_dump($favorite_count_rt);
                        ?>

                        <!-- いいねボタンを押したときのフォーム -->
                        <form action="" method="post" style="display: inline-block;">
                            <input type="hidden" name="post_id" value="<?php print h($post["id"]); ?>">
                            <input type="hidden" name="origin_rt_fav" value="<?php print h((int)$origin_rt_fav); ?>">
                            <input type="hidden" name="rt_fav_refs" value="<?php print h((int)$rt_fav_ref); ?>">
                            <?php if ((int)$favorite_ref["COUNT(*)"] === 0) : ?>
                                <button type="submit" name="favorite" style="background-color: transparent; border:none;"><img class="favorite-image" src="images/heart-solid-gray.svg"></button>
                                <input type="hidden" name="post_id_fav" value="<?php print h($post['id']); ?>">
                        </form>
                        <div style="display: inline-block;"><span style="color:gray;">
                            <?php else : ?>
                                <button type="submit" name="favorite" style="background-color: transparent; border:none;"><img class="favorite-image" src="images/heart-solid-red.svg"></button>
                                <input type="hidden" name="post_id_fav_del" value="<?php print h($post['id']); ?>">
                                </form>
                                <div style="display: inline-block;"><span style="color:red;">
                                    <?php endif; ?>
                                    <?php
                                    //RTされた投稿であれば、RT元のいいね数を表示
                                    //RT元のいいね数
                                    if ((int)$rt_fav_ref === 0) {
                                        echo h((int)$favorite_count_rt["COUNT(*)"]);
                                    } else {
                                        echo h($favorite_count['COUNT(id)']);
                                    }
                                    ?>
                                    </span></div>
                        </div>

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
