<?php

use Carbon\Carbon;
use Laravel\Nova\Nova;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Marshmallow\NovaActivity\Models\NovaActivity;
use Marshmallow\NovaActivity\Resources\NovaActivityCollection;

Route::get('/{resourceName}/{resourceId}/get-comments', function ($resourceName, $resourceId, Request $request) {
    $resource = Nova::resourceForKey($resourceName);
    $model = $resource::newModel()->findOrFail($resourceId);
    return new NovaActivityCollection(
        $model->novaActivity
    );
});

Route::post('/{comment_id}/set-quick-reply', function ($comment_id, Request $request) {
    $comment = NovaActivity::find($comment_id);
    $meta = $comment->meta;
    $meta['quick_replies']['user_' . $request->user()->id] = $request->quick_reply;
    $comment->update([
        'meta' => $meta,
    ]);
});

Route::post('/{comment_id}/run-action', function ($comment_id, Request $request) {
    $comment = NovaActivity::find($comment_id);
    $comment->runAction($request->action);
});

Route::post('/{resourceName}/{resourceId}', function ($resourceName, $resourceId, Request $request) {

    $resource = Nova::resourceForKey($resourceName);
    $model = $resource::newModel()->findOrFail($resourceId);

    try {

        $comment_validation = config('nova-activity.comment_validation');
        if ($comment_validation && !empty($comment_validation)) {
            $request->validate($comment_validation);
        }

        $quick_replies = $request->quick_reply ? [
            'user_' . $request->user()->id => $request->quick_reply,
        ] : [];

        $model->novaActivity()->create([
            'user_id' => $request->user()->id,
            'type_key' => $request->type,
            'type_label' => $request->type_label,
            'comment' => $request->comment,
            'created_at' => Carbon::parse($request->date)->setTimeFromTimeString(
                now()->format('H:i:s')
            ),
            'meta' => [
                'quick_replies' => $quick_replies,
            ],
        ]);

        return [
            'success' => true,
            'message' => 'Comment is created successfully',
        ];
    } catch (Exception $exception) {
        return [
            'success' => false,
            'message' => $exception->getMessage(),
        ];
    }
});
