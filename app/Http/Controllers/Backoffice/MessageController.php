<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\MessageRequest;
use App\Models\Message;
use App\Support\Tenancy\BusinessContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Staff‑to‑tenant messages – the tenant‑portal inbox.
 *
 * Messages are always nested under the business context.  The controller
 * enforces quota (`MessageEntry::assertCanCreate`) and policy checks
 * (`MessagePolicy`) on every read/write operation.
 *
 * Inbox UI uses the design‑system components:
 *   `x-flash-messages`, `x-text-field`, `x-textarea-field`,
 *   `x-select-field`, `x-button`, `x-icon`, `x-confirm-dialog`.
 */
class MessageController extends Controller
{
    public function __construct(
        protected BusinessContext $context,
    ) {}

    /**
     * Display the portal inbox for the active business.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Message::class);

        $query = Message::business();

        // Filter by read status
        if ($request->boolean('unread_only')) {
            $query->unread();
        } elseif ($request->boolean('read_only')) {
            $query->read();
        }

        // Search by subject
        $search = $request->string('search');
        if ($search) {
            $query->where('subject', 'like', '%' . $search . '%');
        }

        $messages = $query->orderBy('created_at', 'desc')
            ->paginate(config('propertyhub.pagination.per_page'))
            ->withQueryString();

        return view('backoffice.messages.index', [
            'messages' => $messages,
            'unreadOnly' => $request->boolean('unread_only'),
            'readOnly' => $request->boolean('read_only'),
            'search' => $search,
        ]);
    }

    /**
     * Show the compose form.
     */
    public function create(): View
    {
        $this->authorize('send', Message::class);

        return view('backoffice.messages.create');
    }

    /**
     * Send a new message to a tenant.
     */
    public store(MessageRequest $request): RedirectResponse
    {
        Message::assertCanCreate($this->context);

        $data = $request->messageAttributes();
        $data['read_at'] = null; // newly created messages are unread

        $message = Message::create($data);

        return redirect()
            ->route('app.messages.index')
            ->with('success', 'Message sent to tenant.');
    }

    /**
     * Mark a message as read (or unread).
     */
    public function readToggle(Message $message): RedirectResponse
    {
        $this->authorize('view', $message);

        // Toggle read status: if currently read, mark unread; if unread, mark read.
        if ($message->read_at) {
            $message->read_at = null;
        } else {
            $message->read_at = now();
        }

        $message->save();

        return redirect()
            ->route('app.messages.index')
            ->with('success', sprintf('Message %s marked %s.', $message->id, $message->isUnread() ? 'unread' : 'read'));
    }

    /**
     * Remove a message.
     */
    public function destroy(Message $message): RedirectResponse
    {
        $this->authorize('delete', $message);

        $subject = $message->subject;

        $message->delete();

        return redirect()
            ->route('app.messages.index')
            ->with('success', sprintf('Message %s was removed.', $subject));
    }

    /**
     * Reply to a message, creating a new message threaded to the original.
     */
    public function reply(MessageRequest $request, Message $parent): RedirectResponse
    {
        $this->authorize('reply', $parent);

        Message::assertCanCreate($this->context);

        $data = $request->messageAttributes();
        $data['read_at'] = null; // newly created replies are unread
        $data['reference_type'] = get_class($parent);
        $data['reference_id'] = $parent->id;

        $message = Message::create($data);

        return redirect()
            ->route('app.messages.index')
            ->with('success', 'Reply sent.');
    }

    /**
     * Attach a file to a message.
     */
    public function attach(MessageRequest $request, Message $message): RedirectResponse
    {
        $this->authorize('attach', $message);

        Message::assertCanCreate($this->context);

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');

            $path = $file->storeAs('public/messages', $file->hashName());

            $message->notes()->create([
                'storage_path' => 'messages/' . Str::after($path, 'storage/'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        return back()->with('success', 'Attachment added.');
    }

    /**
     * Detach an attachment from a message.
     */
    public function detach(Message $message, $attachmentId): RedirectResponse
    {
        $this->authorize('delete', $message);

        $notice = $message->notes()->find($attachmentId);

        if ($notice) {
            $notice->delete();
        }

        return back()->with('success', 'Attachment removed.');
    }

    /**
     * Update message notification preferences.
     */
    public function updatePreferences(MessageRequest $request): RedirectResponse
    {
        $this->authorize('send', Message::class);

        $user = $this->context->user();

        if ($request->has('email_notifications')) {
            $user->email_notifications = $request->boolean('email_notifications');
        }

        if ($request->has('digest_frequency')) {
            $user->digest_frequency = $request->string('digest_frequency');
        }

        $user->save();

        return back()->with('success', 'Notification preferences updated.');
    }
}