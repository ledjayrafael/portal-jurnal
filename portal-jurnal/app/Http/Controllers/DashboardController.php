<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $user = Auth::user();
        
        // Quick stats with optimized queries
        $stats = [
            'total_journals' => Journal::where('user_id', $user->id)->count(),
            'pending' => Journal::where('user_id', $user->id)->where('status', 'pending')->count(),
            'approved' => Journal::where('user_id', $user->id)->where('status', 'approved')->count(),
            'rejected' => Journal::where('user_id', $user->id)->where('status', 'rejected')->count(),
        ];

        // Recent journals (fast query with limit)
        $journals = Journal::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $categories = Category::ordered()->get();

        return view('dashboard.index', compact('stats', 'journals', 'categories'));
    }

    public function create()
    {
        $categories = Category::ordered()->get();
        return view('dashboard.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'abstract' => ['nullable', 'string'],
            'authors' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'keywords' => ['nullable', 'string', 'max:255'],
            'cover_image' => ['nullable', 'image', 'max:2048'],
            'pdf_file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $journal = new Journal();
        $journal->user_id = Auth::id();
        $journal->title = $validated['title'];
        $journal->abstract = $validated['abstract'] ?? null;
        $journal->authors = $validated['authors'];
        $journal->category_id = $validated['category_id'] ?? null;
        $journal->keywords = $validated['keywords'] ?? null;
        $journal->status = 'pending';

        // Upload cover image to R2 Storage
        if ($request->hasFile('cover_image')) {
            $path = $request->file('cover_image')->store('covers', 'r2');
            $journal->cover_image = $path;
        }

        // Upload PDF to R2 Storage
        if ($request->hasFile('pdf_file')) {
            $path = $request->file('pdf_file')->store('journals', 'r2');
            $journal->pdf_file = $path;
            
            // Generate R2 URL (configure in .env)
            $r2Url = config('filesystems.disks.r2.url', '') . '/' . $path;
            $journal->pdf_url = $r2Url;
        }

        $journal->save();

        return redirect()->route('dashboard.journals.index')->with('success', 'Jurnal berhasil diupload!');
    }

    public function edit(Journal $journal)
    {
        $this->authorize('update', $journal);
        $categories = Category::ordered()->get();
        return view('dashboard.edit', compact('journal', 'categories'));
    }

    public function update(Request $request, Journal $journal)
    {
        $this->authorize('update', $journal);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'abstract' => ['nullable', 'string'],
            'authors' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'keywords' => ['nullable', 'string', 'max:255'],
            'cover_image' => ['nullable', 'image', 'max:2048'],
            'pdf_file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $journal->title = $validated['title'];
        $journal->abstract = $validated['abstract'] ?? null;
        $journal->authors = $validated['authors'];
        $journal->category_id = $validated['category_id'] ?? null;
        $journal->keywords = $validated['keywords'] ?? null;

        if ($request->hasFile('cover_image')) {
            if ($journal->cover_image) {
                Storage::disk('r2')->delete($journal->cover_image);
            }
            $path = $request->file('cover_image')->store('covers', 'r2');
            $journal->cover_image = $path;
        }

        if ($request->hasFile('pdf_file')) {
            if ($journal->pdf_file) {
                Storage::disk('r2')->delete($journal->pdf_file);
            }
            $path = $request->file('pdf_file')->store('journals', 'r2');
            $journal->pdf_file = $path;
            $journal->pdf_url = config('filesystems.disks.r2.url', '') . '/' . $path;
        }

        $journal->save();

        return redirect()->route('dashboard.journals.index')->with('success', 'Jurnal berhasil diperbarui!');
    }

    public function destroy(Journal $journal)
    {
        $this->authorize('delete', $journal);

        // Delete files from R2
        if ($journal->cover_image) {
            Storage::disk('r2')->delete($journal->cover_image);
        }
        if ($journal->pdf_file) {
            Storage::disk('r2')->delete($journal->pdf_file);
        }

        $journal->delete();

        return redirect()->route('dashboard.journals.index')->with('success', 'Jurnal berhasil dihapus!');
    }
}
