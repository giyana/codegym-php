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
//[rt]はRTボタンが置かれている投稿id
if (isset($_REQUEST['rt'])) {
    //RT元を取得する(押したRTボタンのある投稿)
    $retweet_originals = $db->prepare('SELECT * FROM posts WHERE id = ?');
    $retweet_originals->execute(array($_REQUEST['rt']));
    $retweet_original = $retweet_originals->fetch();

    if ((int)$retweet_original['retweet_post_id'] === 0) {
        $retweeted_id = $retweet_original['id'];
    } else {
        $retweeted_id = $retweet_original['retweet_post_id'];
    }

    //自分が既にrtしてるかどうか
    $is_retweeted_by_login_user_counts = $db->prepare('SELECT COUNT(*) FROM posts WHERE member_id = ? AND retweet_post_id = ?');
    $is_retweeted_by_login_user_counts->execute(array($member['id'], $retweeted_id));
    $is_retweeted_by_login_user_count = $is_retweeted_by_login_user_counts->fetch(PDO::FETCH_ASSOC);
    $is_retweeted_by_login_user = false;
    if ((int)$is_retweeted_by_login_user_count['COUNT(*)'] !== 0) {
        $is_retweeted_by_login_user = true;
    }
    
    //↑の結果を元にＲＴ投稿・削除処理
    if ($is_retweeted_by_login_user) {
        //RTを削除
        $retweet_delete = $db->prepare('DELETE FROM posts WHERE member_id = ? AND retweet_post_id = ?');
        $retweet_delete->execute(array($member['id'], $retweeted_id));
    } else {
        //RTを投稿
        $retweet_posts = $db->prepare('INSERT INTO posts SET member_id = ?, retweet_post_id = ?, created=NOW()');
        $retweet_posts->execute(array($member['id'],(int)$retweeted_id));
    }

    header('Location: index.php');
    exit();
}

//ボタンを押すとfavoritesテーブルにデータ挿入＆削除
if (isset($_POST['favorite'])) {
    //すでにいいねしてる場合削除
    if (isset($_POST['post_id_fav_del'])) {
        //元投稿か、RTされているいいねなのか判別して削除
        $favorites_del = $db->prepare('DELETE FROM favorites WHERE member_id = ? AND post_id = ?');
        if ((int)$_POST['retweet_post_id'] !== 0) {
            $favorites_del->execute(array(
                $member['id'],
                (int)$_POST['retweet_post_id']
            ));
        } else {
            //元投稿
            $favorites_del->execute(array(
                $member['id'],
                $_POST["post_id"]
            ));
        }
    } else {
        //RTされた投稿に対してのいいねは、post_idをRT元のものにする
        if ((int)$_POST['retweet_post_id'] !== 0) {
            //RTされた投稿に対するいいね
            $favorites_rt = $db->prepare('INSERT INTO favorites SET member_id = ?, post_id = ?, created=NOW()');
            $favorites_rt->execute(array(
                $member['id'],
                $_POST['retweet_post_id']
            ));
        } else {
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
                        <!-- RTフォーム -->
                    <div class="retweet">
                        <?php

                        //ログインしている人がRTしているかどうか 元RTの場合0を出力
                        $retweets_ref = $db->prepare('SELECT * FROM posts WHERE member_id = ? AND retweet_post_id = ? ');
                        $retweets_ref->execute(array($member['id'], $post['id']));
                        $retweet_ref = $retweets_ref->fetch();

                        //RT数を取得
                        $rt_counts = $db->prepare('SELECT COUNT(*) FROM posts WHERE retweet_post_id = ?');
                        if ((int)$post["retweet_post_id"] === 0) {
                            //元投稿の場合
                            $rt_counts->execute(array($post['id']));
                        } else {
                            //RT投稿の場合
                            $rt_counts->execute(array($post['retweet_post_id']));
                        }
                        $rt_count = $rt_counts->fetch(PDO::FETCH_ASSOC);

                        //RT数0なら空文字出力
                        if ((int)$rt_count["COUNT(*)"] === 0) {
                            $rt_count = "";
                        } else {
                            $rt_count = (int)$rt_count["COUNT(*)"];
                        }

                        //ログインしている人がRTした投稿のid 元投稿なら0 
                        $login_pre_rt_ids = $db->prepare('SELECT * FROM posts WHERE id = ? AND member_id = ?');
                        $login_pre_rt_ids->execute(array(
                            $post["id"],
                            $member["id"]
                        ));
                        $login_pre_rt_id = $login_pre_rt_ids->fetch();

                        //RT色分け RT元とRTされたもの
                        if ((int)$retweet_ref["retweet_post_id"] !== 0 || (int)$login_pre_rt_id["retweet_post_id"] !== 0) {
                            $rt_colors = "blue";
                        } else {
                            $rt_colors = "gray";
                        }
                        ?>
                        <!-- RTボタン -->
                        <a href="index.php?rt=<?php echo h($post['id']); ?>">
                            <img class="retweet-image" src="images/retweet-solid-<?php echo h($rt_colors) ?>.svg"></a>
                        <span style="color:<?php echo h($rt_colors) ?>;">
                            <?php echo h($rt_count); ?>
                        </span>
                    </div>

                    <!-- いいねフォーム -->
                    <!-- ログインしている人が各投稿をいいねしているかどうかで色替え条件分岐
                        各投稿をいいねしていいるか、過去RTをいいねしているか-->
                    <div class="favorite">
                        <?php
                        //各投稿のいいね数を取得
                        $fav_counts = $db->prepare('SELECT COUNT(*) FROM favorites WHERE post_id=?');
                        if ((int)$post["retweet_post_id"] === 0) {
                            $fav_counts->execute(array($post['id']));
                        } else {
                            $fav_counts->execute(array($post['retweet_post_id']));
                        }
                        $fav_count = $fav_counts->fetch(PDO::FETCH_ASSOC);

                        //fav数0なら空文字出力
                        if ((int)$fav_count["COUNT(*)"] === 0) {
                            $fav_count = "";
                        } else {
                            $fav_count = (int)$fav_count["COUNT(*)"];
                        }

                        //ログインしている人が各投稿をいいねしている数
                        $login_favs = $db->prepare('SELECT COUNT(*) FROM favorites WHERE member_id = ? AND post_id = ?');
                        $login_favs->execute(array(
                            $member['id'],
                            $post['id']
                        ));
                        $login_fav = $login_favs->fetch(PDO::FETCH_ASSOC);

                        //ログインしている人が、各RTされた投稿に対しいいねしている数
                        $login_rt_favs = $db->prepare('SELECT COUNT(*) FROM favorites WHERE member_id = ? AND post_id = ?');
                        $login_rt_favs->execute(array(
                            $member['id'],
                            $post['retweet_post_id']
                        ));
                        $login_rt_fav = $login_rt_favs->fetch(PDO::FETCH_ASSOC);

                        //fav色分け 
                        if ((int)$login_fav["COUNT(*)"] !== 0 || (int)$login_rt_fav["COUNT(*)"] !== 0) {
                            $fav_colors = "red";
                        } else {
                            $fav_colors = "gray";
                        }
                        ?>

                        <!-- いいねボタンを押したときのフォーム -->
                        <form action="" method="post" style="display: inline-block;">
                            <input type="hidden" name="post_id" value="<?php print h($post["id"]); ?>">
                            <input type="hidden" name="retweet_post_id" value="<?php print h($post["retweet_post_id"]); ?>">
                            <!-- <input type="hidden" name="rt_fav_refs" value="<?php print h((int)$rt_fav_ref); ?>"> -->
                            <button type="submit" name="favorite" style="background-color: transparent; border:none;">
                                <img class="favorite-image" src="images/heart-solid-<?php echo h($fav_colors); ?>.svg"></button>

                            <?php if ((int)$login_fav["COUNT(*)"] === 0 && (int)$login_rt_fav["COUNT(*)"] === 0) : ?>
                            <?php else : ?>
                                <input type="hidden" name="post_id_fav_del" value="<?php print h($post['id']); ?>">
                            <?php endif; ?>
                        </form>
                        <div style="display: inline-block;">
                            <span style="color:<?php echo h($fav_colors) ?>;"><?php echo h($fav_count); ?></span>
                        </div>
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
