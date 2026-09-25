{{--
    The canvas document stylesheet for a tenant page.

    Emits the stored `public.canvas.nodes` map (local rules, pseudo-class
    states, breakpoint rules, `display:none` for hidden nodes) as one style
    tag. Node ids are namespaced by page root — `public.landing.*`,
    `public.consultant.*`, `public.student.*`, `public.teacher.*` — so the same
    sheet can be emitted on every page without a rule matching an element it
    was not written for. Degrades to nothing when a tenant has no nodes.
--}}
@php($studioNodes = \App\Support\StudioStyles::nodesCss(site('public.canvas.nodes')))
@if($studioNodes !== '')
    <style id="studio-node-styles">{!! $studioNodes !!}</style>
@endif
