"""BCP 1979 Daily Devotions, pp. 136–140; reusable contemporary-language text."""
LORDS_PRAYER = "Our Father, who art in heaven, hallowed be thy Name, thy kingdom come, thy will be done, on earth as it is in heaven. Give us this day our daily bread. And forgive us our trespasses, as we forgive those who trespass against us. And lead us not into temptation, but deliver us from evil. For thine is the kingdom, and the power, and the glory, for ever and ever. Amen."
DEVOTIONS = {
    'morning-prayer': {
        'title': 'In the Morning',
        'opening': ('From Psalm 51', 'Open my lips, O Lord, and my mouth shall proclaim your praise. Create in me a clean heart, O God, and renew a right spirit within me. Cast me not away from your presence and take not your holy Spirit from me. Give me the joy of your saving help again and sustain me with your bountiful Spirit. Glory to the Father, and to the Son, and to the Holy Spirit: as it was in the beginning, is now, and will be for ever. Amen.'),
        'reading': 'Blessed be the God and Father of our Lord Jesus Christ! By his great mercy we have been born anew to a living hope through the resurrection of Jesus Christ from the dead. First Peter, chapter 1, verse 3.',
        'collect': 'Lord God, almighty and everlasting Father, you have brought us in safety to this new day: Preserve us with your mighty power, that we may not fall into sin, nor be overcome by adversity; and in all we do, direct us to the fulfilling of your purpose; through Jesus Christ our Lord. Amen.'},
    'noonday-prayer': {
        'title': 'At Noon',
        'opening': ('From Psalm 113', 'Give praise, you servants of the LORD; praise the Name of the LORD. Let the Name of the LORD be blessed, from this time forth for evermore. From the rising of the sun to its going down let the Name of the LORD be praised. The LORD is high above all nations, and his glory above the heavens.'),
        'reading': 'O God, you will keep in perfect peace those whose minds are fixed on you; for in returning and rest we shall be saved; in quietness and trust shall be our strength. Isaiah, chapter 26, verse 3, and chapter 30, verse 15.',
        'collect': 'Lord Jesus Christ, you said to your apostles, "Peace I give to you; my own peace I leave with you:" Regard not our sins, but the faith of your Church; and give to us the peace and unity of that heavenly City, where with the Father and the Holy Spirit you live and reign, now and for ever. Amen.'},
    'evening-prayer': {
        'title': 'In the Early Evening',
        'opening': ('O gracious light', 'O gracious light, pure brightness of the everliving Father in heaven, O Jesus Christ, holy and blessed! Now as we come to the setting of the sun, and our eyes behold the vesper light, we sing your praises, O God: Father, Son, and Holy Spirit. You are worthy at all times to be praised by happy voices, O Son of God, O Giver of Life, and to be glorified through all the worlds.'),
        'reading': 'It is not ourselves that we proclaim; we proclaim Christ Jesus as Lord, and ourselves as your servants, for Jesus\u2019 sake. For the same God who said, "Out of darkness let light shine," has caused his light to shine within us, to give the light of revelation\u2014the revelation of the glory of God in the face of Jesus Christ. Second Corinthians, chapter 4, verses 5 and 6.',
        'collect': 'Lord Jesus, stay with us, for evening is at hand and the day is past; be our companion in the way, kindle our hearts, and awaken hope, that we may know you as you are revealed in Scripture and the breaking of bread. Grant this for the sake of your love. Amen.'},
    'compline': {
        'title': 'At the Close of Day',
        'opening': ('Psalm 134', 'Behold now, bless the LORD, all you servants of the LORD, you that stand by night in the house of the LORD. Lift up your hands in the holy place and bless the LORD; the LORD who made heaven and earth bless you out of Zion.'),
        'reading': 'Lord, you are in the midst of us and we are called by your Name: Do not forsake us, O Lord our God. Jeremiah, chapter 14, verses 9 and 22.',
        'collect': 'Visit this place, O Lord, and drive far from it all snares of the enemy; let your holy angels dwell with us to preserve us in peace; and let your blessing be upon us always; through Jesus Christ our Lord. Amen.'}
}


def devotion(office):
    d = DEVOTIONS[office]
    blocks = []
    def add(kind, text):
        blocks.append({'kind': kind, 'text': text})
    add('heading', 'A Brief Devotion ' + d['title'])
    add('text', 'From Daily Devotions for Individuals and Families in the Book of Common Prayer.')
    first = len(blocks)
    add('heading', d['opening'][0])
    add('text', d['opening'][1])
    add('heading', 'A Reading')
    add('text', d['reading'])
    add('heading', 'Prayers')
    add('text', 'Prayers may be offered for ourselves and others.')
    pause_after = len(blocks) - 1
    add('heading', "The Lord\u2019s Prayer")
    add('text', LORDS_PRAYER)
    add('heading', 'The Collect')
    add('text', d['collect'])
    if office == 'compline':
        add('text', 'The almighty and merciful Lord, Father, Son, and Holy Spirit, bless us and keep us. Amen.')
    last = len(blocks) - 1
    study_first = len(blocks)
    summary = 'This short devotion makes room for Scripture and prayer within the rhythm of the day. Take a moment to notice one phrase that speaks to your life, and carry it with you.'
    sections = [
        {'title': 'Opening praise and Scripture', 'text': 'We turn our attention to God through a psalm or song of praise, then listen to a brief reading from Scripture.'},
        {'title': 'Praying together', 'text': 'Name people who need care and give thanks for the blessings you have received. In a family, each person may share, or listen quietly.'},
        {'title': 'The Lord\u2019s Prayer and collect', 'text': 'Jesus\u2019 prayer joins our needs to the prayer of the whole Church. The collect gathers our intentions into a prayer suited to this time of day.'}]
    add('heading', 'A Guide to This Devotion')
    add('text', summary)
    for item in sections:
        add('heading', item['title'])
        add('text', item['text'])
    return {'office': office, 'rite': 'contemporary', 'title': 'Brief Devotion ' + d['title'],
            'blocks': blocks, 'devotion_version': 1, 'companion_version': 1,
            'family_pause_after': pause_after,
            'segments': {'office_first': first, 'office_last': last, 'study_first': study_first},
            'study': {'label': 'Guide to the brief devotion · separate from the BCP liturgy.', 'summary': summary, 'sections': sections},
            'attribution': 'Book of Common Prayer (1979), Daily Devotions for Individuals and Families, pp. 136–140. Guide and family prompts are separate devotional resources.'}
