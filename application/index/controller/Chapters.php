<?php
/**
 * Created by PhpStorm.
 * User: zhangxiang
 * Date: 2018/10/18
 * Time: 下午5:42
 */

namespace app\index\controller;

use app\model\Chapter;
use think\Db;

class Chapters extends Base
{
    public function index($id)
    {
        $chapter = Chapter::with(['photos' => function ($query) {
            $query->order('id');
        }], 'book')->cache('chapter' . $id,600,'redis')->find($id);
        $book_id = $chapter->book_id;
        $chapters = cache('mulu'.$book_id);
        if (!$chapters){
            $chapters = Chapter::where('book_id','=',$book_id)->select();
            cache('mulu'.$book_id,$chapters,null,'redis');
        }

        $uid = session('xwx_user_id');
        if ($uid){
            $redis = new_redis();
            $arr = [
                'book_id' => $chapter->book->id,
                'cover' => $chapter->book->cover_url,
                'chapter_id' => $chapter->id,
                'chapter_name' => $chapter->chapter_name,
                'book_name' => $chapter->book->book_name,
                'author_name' => $chapter->book->author->author_name,
                'end' => $chapter->book->end,
                'last_time' => $chapter->book->last_time
            ];
            $redis->hSet('history:'.$uid,$chapter->book->id,json_encode($arr)); //利用hash表，保证用户及book的唯一性
            $redis->rPush('history:log',$chapter->book->id); //将key记录进队列，用于日后按顺序删除
            if ($redis->hLen('history:'.$uid) > 10){
                $key = $redis->lPop('history:log'); //拿到队列最早的key
                $redis->hDel('history:'.$uid,$key); //按照key从hash表删除
            }
        }
        $prev = cache('chapter_prev'.$id);
        if (!$prev){
            $prev = Db::query(
                'select * from '.$this->prefix.'chapter where book_id='.$book_id.' and `order`<' . $chapter->order . ' order by id desc limit 1');
            cache('chapter_prev'.$id,$prev,null,'redis');
        }
        $next = cache('chapter_next'.$id);
        if (!$next){
            $next = Db::query(
                'select * from '.$this->prefix.'chapter where book_id='.$book_id.' and `order`>' . $chapter->order . ' order by id limit 1');
            cache('chapter_next'.$id,$next,null,'redis');
        }
        if (count($prev) > 0) {
            $this->assign('prev', $prev[0]);
        } else {
            $this->assign('prev', 'null');
        }
        if (count($next) > 0) {
            $this->assign('next', $next[0]);
        } else {
            $this->assign('next', 'null');
        }
        $this->assign([
            'chapter' => $chapter,
            'chapters' => $chapters,
            'photos' => $chapter->photos,
            'chapter_count' => count($chapters),
            'site_name' => config('site.site_name')
        ]);
        return view($this->tpl);
    }
}